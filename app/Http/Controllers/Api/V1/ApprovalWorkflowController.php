<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\StoreWorkflowRequest;
use App\Http\Resources\V1\ApprovalWorkflowResource;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('approvals.manage'), 403);

        $workflows = ApprovalWorkflow::query()
            ->with('steps')
            ->withCount('requests')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return $this->success(ApprovalWorkflowResource::collection($workflows)->resolve());
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('approvals.manage'), 403);

        $workflow = DB::transaction(function () use ($request): ApprovalWorkflow {
            $workflow = ApprovalWorkflow::create([
                'name'        => $request->validated('name'),
                'entity_type' => $request->validated('entity_type'),
                'description' => $request->validated('description'),
                'is_active'   => (bool) $request->validated('is_active', false),
                'created_by'  => $request->user()->id,
            ]);

            $this->syncSteps($workflow, $request->validated('steps'));

            if ($workflow->is_active) {
                $this->deactivateSiblings($workflow);
            }

            return $workflow;
        });

        return $this->success(
            new ApprovalWorkflowResource($workflow->fresh()->loadMissing('steps')),
            'Workflow created.',
            201,
        );
    }

    public function update(StoreWorkflowRequest $request, ApprovalWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->can('approvals.manage'), 403);

        DB::transaction(function () use ($request, $workflow): void {
            $workflow->update([
                'name'        => $request->validated('name'),
                'entity_type' => $request->validated('entity_type'),
                'description' => $request->validated('description'),
                'is_active'   => (bool) $request->validated('is_active', $workflow->is_active),
            ]);

            $this->syncSteps($workflow, $request->validated('steps'));

            if ($workflow->is_active) {
                $this->deactivateSiblings($workflow);
            }
        });

        return $this->success(
            new ApprovalWorkflowResource($workflow->fresh()->loadMissing('steps')),
            'Workflow updated.',
        );
    }

    public function destroy(Request $request, ApprovalWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->can('approvals.manage'), 403);

        if ($workflow->requests()->where('status', 'pending')->exists()) {
            return $this->error('This workflow still has pending requests running on it.', 409);
        }

        $workflow->delete();

        return $this->success(message: 'Workflow deleted.');
    }

    // ── Internals ─────────────────────────────────────────────

    private function syncSteps(ApprovalWorkflow $workflow, array $steps): void
    {
        $workflow->steps()->delete();

        foreach (array_values($steps) as $index => $step) {
            ApprovalWorkflowStep::create([
                'approval_workflow_id' => $workflow->id,
                'step_number'          => $index + 1,
                'name'                 => $step['name'],
                'approver_source'      => $step['approver_source'],
                'approver_role'        => $step['approver_role'] ?? null,
                'approver_user_ids'    => isset($step['approver_users']) && $step['approver_users'] !== []
                    ? User::query()->whereIn('uuid', $step['approver_users'])->pluck('id')->values()->all()
                    : null,
                'policy'               => $step['policy'],
                'escalation_days'      => $step['escalation_days'] ?? null,
            ]);
        }
    }

    private function deactivateSiblings(ApprovalWorkflow $workflow): void
    {
        ApprovalWorkflow::query()
            ->where('entity_type', $workflow->entity_type)
            ->where('id', '!=', $workflow->id)
            ->update(['is_active' => false]);
    }
}