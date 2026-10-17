<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Laravel\Sanctum\PersonalAccessToken
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'current'      => $request->user()?->currentAccessToken()?->id === $this->id,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}