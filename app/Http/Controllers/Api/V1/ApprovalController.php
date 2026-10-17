<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Approvals\ApprovalEngine;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ApprovalRequestResource;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalEngine $approvals) {}

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $scope = (string) $request->query('scope', 'pending');

        if (! in_array($scope, ['pending', 'requested', 'all'], true)) {
            $scope = 'pending';
        }

        if ($scope === 'pending') {
            abort_unless($user->can('approvals.decide') || $user->can('approvals.view'), 403);
        }

        if ($scope === 'all') {
            abort_unless($user->can('approvals.view'), 403);
        }

        $query = ApprovalRequest::query()->with(['requester', 'steps.decisions.user']);

        $requests = match ($scope) {
            'requested' => $query->where('requested_by', $user->id)->latest('id')->get(),
            'all'       => $query->latest('id')->get(),
            default     => $query->where('status', 'pending')->orderBy('created_at')->get()
                ->filter(fn (ApprovalRequest $approvalRequest) => $this->approvals->canDecide($approvalRequest, $user))
                ->values(),
        };

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $paginator = new LengthAwarePaginator(
            $requests->slice(($page - 1) * $perPage, $perPage)->values(),
            $requests->count(),
            $perPage,
            $page,
        );

        return $this->paginated($paginator, ApprovalRequestResource::class);
    }

    public function show(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $user = $request->user();

        $allowed = $approvalRequest->requested_by === $user->id
            || $user->can('approvals.view')
            || $this->approvals->canDecide($approvalRequest, $user)
            || ApprovalDecision::query()
                ->where('approval_request_id', $approvalRequest->id)
                ->where('user_id', $user->id)
                ->exists();

        abort_unless($allowed, 403);

        $approvalRequest->loadMissing(['requester', 'steps.decisions.user', 'workflow']);

        return $this->success(new ApprovalRequestResource($approvalRequest));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        return $this->decide($request, $approvalRequest, true);
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        return $this->decide($request, $approvalRequest, false);
    }

    private function decide(Request $request, ApprovalRequest $approvalRequest, bool $approved): JsonResponse
    {
        abort_unless($request->user()->can('approvals.decide') || $request->user()->can('expenses.approve'), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        $this->approvals->recordDecision($approvalRequest, $request->user(), $approved, $note);

        $approvalRequest = $approvalRequest->fresh(['requester', 'steps.decisions.user', 'workflow']);

        return $this->success(
            new ApprovalRequestResource($approvalRequest),
            $approved ? 'Request approved.' : 'Request rejected.',
        );
    }
}