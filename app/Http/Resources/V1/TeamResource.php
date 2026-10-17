<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Team
 */
class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Team Identity
            |--------------------------------------------------------------------------
            */
            'id' => $this->uuid,

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            'is_active' => (bool) $this->is_active,

            /*
            |--------------------------------------------------------------------------
            | Department
            |--------------------------------------------------------------------------
            |
            | Included only when the department relationship has been
            | eager loaded.
            |
            */
            'department' => $this->whenLoaded(
                'department',
                fn () => $this->department !== null
                    ? [
                        'id' => $this->department->uuid,
                        'name' => $this->department->name,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Team Leader
            |--------------------------------------------------------------------------
            |
            | Included only when the leader relationship has been
            | eager loaded.
            |
            */
            'leader' => $this->whenLoaded(
                'leader',
                fn () => $this->leader !== null
                    ? [
                        'id' => $this->leader->uuid,
                        'full_name' => trim(
                            $this->leader->first_name . ' ' . $this->leader->last_name
                        ),
                        'job_title' => $this->leader->job_title,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Team Members Count
            |--------------------------------------------------------------------------
            |
            | The underlying Eloquent relationship is "employees".
            |
            | The controller/query should use:
            |
            |     ->withCount('employees')
            |
            | Laravel then provides:
            |
            |     employees_count
            |
            | We expose it through the API as:
            |
            |     members_count
            |
            | IMPORTANT:
            | Use whenCounted(), not whenCountLoaded().
            |
            */
            'members_count' => $this->whenCounted('employees'),

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}