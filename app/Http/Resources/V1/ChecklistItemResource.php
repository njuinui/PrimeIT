<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TaskChecklistItem
 */
class ChecklistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->uuid,
            'content'      => $this->content,
            'is_completed' => (bool) $this->is_completed,
            'sort_order'   => (int) $this->sort_order,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}