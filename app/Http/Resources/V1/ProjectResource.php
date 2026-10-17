<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */
            'id'             => $this->uuid,
            'project_number' => $this->project_number,
            'name'           => $this->name,
            'slug'           => $this->slug,

            /*
            |--------------------------------------------------------------------------
            | Project Information
            |--------------------------------------------------------------------------
            */
            'description' => $this->description,
            'status'      => $this->status,
            'priority'    => $this->priority,
            'progress'    => (int) $this->progress,

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */
            'start_date'   => $this->start_date?->toDateString(),
            'due_date'     => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toISOString(),

            /*
            |--------------------------------------------------------------------------
            | Financial Information
            |--------------------------------------------------------------------------
            */
            'budget'   => $this->budget !== null
                ? (float) $this->budget
                : null,

            'currency' => $this->currency,

            /*
            |--------------------------------------------------------------------------
            | Audit Information
            |--------------------------------------------------------------------------
            */
            'created_at' => $this->created_at?->toISOString(),

            /*
            |--------------------------------------------------------------------------
            | Owner
            |--------------------------------------------------------------------------
            */
            'owner' => $this->whenLoaded(
                'owner',
                fn () => $this->owner !== null
                    ? [
                        'id'        => $this->owner->uuid,
                        'full_name' => trim(
                            $this->owner->first_name . ' ' . $this->owner->last_name
                        ),
                        'job_title' => $this->owner->job_title,
                        'avatar_url' => $this->owner->avatar ? Storage::disk('public')->url($this->owner->avatar) : null,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Project Manager
            |--------------------------------------------------------------------------
            */
            'project_manager' => $this->whenLoaded(
                'projectManager',
                fn () => $this->projectManager !== null
                    ? [
                        'id'        => $this->projectManager->uuid,
                        'full_name' => trim(
                            $this->projectManager->first_name . ' ' . $this->projectManager->last_name
                        ),
                        'job_title' => $this->projectManager->job_title,
                        'avatar_url' => $this->projectManager->avatar ? Storage::disk('public')->url($this->projectManager->avatar) : null,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Department
            |--------------------------------------------------------------------------
            */
            'department' => $this->whenLoaded(
                'department',
                fn () => $this->department !== null
                    ? [
                        'id'   => $this->department->uuid,
                        'name' => $this->department->name,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Relationship Counts
            |--------------------------------------------------------------------------
            |
            | ProjectController uses:
            |
            | ->withCount(['members', 'milestones', 'risks'])
            |
            | Therefore Laravel exposes:
            |
            | members_count
            | milestones_count
            | risks_count
            |
            | JsonResource uses whenCounted() for these relationship counts.
            |--------------------------------------------------------------------------
            */
            'members_count' => $this->whenCounted('members'),

            'milestones_count' => $this->whenCounted('milestones'),

            'risks_count' => $this->whenCounted('risks'),

            /*
            |--------------------------------------------------------------------------
            | Task Counts
            |--------------------------------------------------------------------------
            |
            | These are aliases created by ProjectController:
            |
            | ->withCount('tasks as total_tasks')
            | ->withCount(['tasks as open_tasks' => ...])
            | ->withCount(['tasks as completed_tasks' => ...])
            |
            | Because these are aliases rather than normal relationship counts,
            | we check whether the attributes exist directly.
            |--------------------------------------------------------------------------
            */
            'total_tasks' => $this->when(
                isset($this->total_tasks),
                (int) ($this->total_tasks ?? 0)
            ),

            'open_tasks' => $this->when(
                isset($this->open_tasks),
                (int) ($this->open_tasks ?? 0)
            ),

            'completed_tasks' => $this->when(
                isset($this->completed_tasks),
                (int) ($this->completed_tasks ?? 0)
            ),

            /*
            |--------------------------------------------------------------------------
            | Project Members
            |--------------------------------------------------------------------------
            |
            | This is only included when the controller has loaded the
            | members relationship using ->with('members').
            |--------------------------------------------------------------------------
            */
            'members' => $this->whenLoaded(
                'members',
                fn () => ProjectMemberResource::collection($this->members)
            ),

            /*
            |--------------------------------------------------------------------------
            | Permission / Capability Flags
            |--------------------------------------------------------------------------
            */
            'can_update' => $request->user()?->can(
                'update',
                $this->resource
            ) ?? false,

            'can_delete' => $request->user()?->can(
                'delete',
                $this->resource
            ) ?? false,

            'can_manage_members' => $request->user()?->can(
                'manageMembers',
                $this->resource
            ) ?? false,
        ];
    }
}