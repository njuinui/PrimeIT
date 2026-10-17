<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TaskTimeEntry
 */
class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->uuid,
            'minutes'    => (int) $this->minutes,
            'note'       => $this->note,
            'worked_at'  => $this->worked_at?->toDateString(),
            'user'       => $this->whenLoaded('user', fn () => $this->user?->name),
            'created_at' => $this->created_at?->toISOString(),

            'can_delete' => $request->user()?->id === $this->user_id,
        ];
    }
}