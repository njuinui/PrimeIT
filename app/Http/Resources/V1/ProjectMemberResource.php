<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Employee
 */
class ProjectMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->uuid,
            'full_name'  => trim($this->first_name.' '.$this->last_name),
            'job_title'  => $this->job_title,
            'avatar_url' => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,
            'department' => $this->whenLoaded('department', fn () => $this->department?->name),
            'role'       => $this->pivot?->role,
            'joined_at'  => $this->pivot?->created_at?->toISOString(),
        ];
    }
}