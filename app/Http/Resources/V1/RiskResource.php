<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProjectRisk
 */
class RiskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->uuid,
            'title'       => $this->title,
            'description' => $this->description,
            'impact'      => $this->impact,
            'likelihood'  => $this->likelihood,
            'status'      => $this->status,
            'mitigation'  => $this->mitigation,
            'review_date' => $this->review_date?->toDateString(),
            'created_at'  => $this->created_at?->toISOString(),

            'owner' => $this->whenLoaded('owner', fn () => $this->owner !== null ? [
                'id'        => $this->owner->uuid,
                'full_name' => trim($this->owner->first_name.' '.$this->owner->last_name),
            ] : null),
        ];
    }
}