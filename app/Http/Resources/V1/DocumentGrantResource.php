<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DocumentGrant
 */
class DocumentGrantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user'       => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->uuid,
                'name' => $this->user->name,
            ]),
            'ability'    => $this->ability,
            'granted_by' => $this->whenLoaded('grantedBy', fn () => $this->grantedBy?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}