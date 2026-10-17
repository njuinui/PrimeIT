<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Department Identity
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
            | Department Manager
            |--------------------------------------------------------------------------
            |
            | The controller loads the manager using:
            |     ->with('manager')
            |
            | We only include the manager when that relationship has
            | actually been loaded.
            |
            */
            'manager' => $this->whenLoaded(
                'manager',
                fn () => $this->manager !== null
                    ? [
                        'id' => $this->manager->uuid,
                        'full_name' => trim(
                            $this->manager->first_name . ' ' . $this->manager->last_name
                        ),
                        'job_title' => $this->manager->job_title,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Relationship Counts
            |--------------------------------------------------------------------------
            |
            | The controller uses:
            |
            |     ->withCount(['employees', 'teams'])
            |
            | Therefore Laravel provides:
            |
            |     employees_count
            |     teams_count
            |
            | Use whenCounted(), NOT whenCountLoaded().
            |
            */
            'employees_count' => $this->whenCounted('employees'),
            'teams_count' => $this->whenCounted('teams'),

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