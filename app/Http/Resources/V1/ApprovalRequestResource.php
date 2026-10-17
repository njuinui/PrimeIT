<?php

namespace App\Http\Resources\V1;

use App\Domain\Approvals\ApprovalEngine;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @mixin \App\Models\ApprovalRequest
 */
class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $engine    = app(ApprovalEngine::class);
        $user      = $request->user();
        $approvable = $this->approvable()->first();

        $subject = null;

        if ($approvable instanceof Expense) {
            $subject = [
                'type'    => 'expense',
                'id'      => $approvable->uuid,
                'number'  => $approvable->expense_number,
                'title'   => $approvable->title,
                'amount'  => (float) $approvable->amount,
                'currency' => $approvable->currency,
                'status'  => $approvable->status,
                'date'    => $approvable->expense_date?->toDateString(),
            ];
        }

        return [
            'id'            => $this->uuid,
            'status'        => $this->status,
            'current_step'  => (int) $this->current_step,
            'total_steps'   => (int) $this->steps()->count(),
            'workflow_name' => $this->workflow()->value('name'),
            'requested_at'  => $this->created_at?->toISOString(),
            'completed_at'  => $this->completed_at?->toISOString(),

            'requester' => $this->whenLoaded('requester', fn () => [
                'id'   => $this->requester->uuid,
                'name' => $this->requester->name,
            ]),

            'subject' => $subject,

            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(function ($step) use ($engine) {
                $approverIds = $engine->approverUserIds($step);

                $approvers = $approverIds === []
                    ? []
                    : DB::table('users')->whereIn('id', $approverIds)->get(['id', 'uuid', 'name'])
                        ->map(fn ($user) => ['id' => $user->uuid, 'name' => $user->name])
                        ->values()->all();

                return [
                    'id'              => $step->uuid,
                    'step_number'     => (int) $step->step_number,
                    'name'            => $step->name,
                    'status'          => $step->status,
                    'policy'          => $step->policy,
                    'approver_source' => $step->approver_source,
                    'approver_role'   => $step->approver_role,
                    'decided_at'      => $step->decided_at?->toISOString(),
                    'escalated_at'    => $step->escalated_at?->toISOString(),
                    'approvers'       => $approvers,
                    'decisions'       => $step->relationLoaded('decisions')
                        ? $step->decisions->map(fn ($decision) => [
                            'id'         => $decision->uuid,
                            'user'       => $decision->relationLoaded('user') ? $decision->user?->name : null,
                            'decision'   => $decision->decision,
                            'note'       => $decision->note,
                            'created_at' => $decision->created_at?->toISOString(),
                        ])->all()
                        : [],
                ];
            })->all()),

            'can_decide' => $user !== null ? $engine->canDecide($this->resource, $user) : false,
        ];
    }
}