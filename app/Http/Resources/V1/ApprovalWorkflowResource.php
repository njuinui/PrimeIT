<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ApprovalWorkflow
 */
class ApprovalWorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->uuid,
            'name'        => $this->name,
            'entity_type' => $this->entity_type,
            'description' => $this->description,
            'is_active'   => (bool) $this->is_active,
            'created_by'  => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator?->uuid,
                'name' => $this->creator?->name,
            ]),
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($step) => [
                'id'                 => $step->uuid,
                'step_number'        => (int) $step->step_number,
                'name'              => $step->name,
                'approver_source'   => $step->approver_source,
                'approver_role'     => $step->approver_role,
                'policy'            => $step->policy,
                'escalation_days'   => $step->escalation_days,
                'approver_user_ids' => $step->approver_user_ids,
            ])->all()),
            'requests_count' => $this->whenCounted('requests', $this->requests_count),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
