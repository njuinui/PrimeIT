<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProjectMilestone
 */
class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->uuid,
            'title'        => $this->title,
            'description'  => $this->description,
            'due_date'     => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'is_completed' => $this->completed_at !== null,
            'sort_order'   => (int) $this->sort_order,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}