<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProjectActivity
 */
class ProjectActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'event'       => $this->event,
            'description' => $this->description,
            'actor'       => $this->whenLoaded('user', fn () => $this->user !== null ? [
                'id'   => $this->user->uuid,
                'name' => $this->user->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}