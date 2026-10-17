<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Budget
 */
class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->uuid,
            'description' => $this->description,
            'amount'      => (float) $this->amount,
            'currency'    => $this->currency,
            'start_date'  => $this->start_date?->toDateString(),
            'end_date'    => $this->end_date?->toDateString(),
            'created_at'  => $this->created_at?->toISOString(),

            'subject_type' => $this->project_id !== null ? 'project' : 'department',

            'subject' => $this->when(
                $this->relationLoaded('project') || $this->relationLoaded('department'),
                function () {
                    if ($this->project_id !== null && $this->relationLoaded('project') && $this->project !== null) {
                        return ['id' => $this->project->uuid, 'name' => $this->project->name];
                    }

                    if ($this->relationLoaded('department') && $this->department !== null) {
                        return ['id' => $this->department->uuid, 'name' => $this->department->name];
                    }

                    return null;
                },
            ),

            'spent'    => $this->when(isset($this->spent), (float) ($this->spent ?? 0)),
            'remaining' => $this->when(isset($this->spent), max(0.0, (float) $this->amount - (float) ($this->spent ?? 0))),
        ];
    }
}