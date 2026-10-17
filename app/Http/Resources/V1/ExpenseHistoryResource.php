<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExpenseHistory
 */
class ExpenseHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->uuid,
            'event'       => $this->event,
            'from_status' => $this->from_status,
            'to_status'   => $this->to_status,
            'note'        => $this->note,
            'actor'       => $this->whenLoaded('user', fn () => $this->user?->name ?? 'System'),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}