<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->uuid,
            'task_number'       => $this->task_number,
            'title'             => $this->title,
            'description'       => $this->description,
            'status'            => $this->status,
            'priority'          => $this->priority,
            'progress'          => (int) $this->progress,
            'start_date'        => $this->start_date?->toISOString(),
            'due_date'          => $this->due_date?->toISOString(),
            'completed_at'      => $this->completed_at?->toISOString(),
            'estimated_minutes' => $this->estimated_minutes,
            'created_at'        => $this->created_at?->toISOString(),

            'is_overdue' => (bool) $this->is_overdue,

            'project' => $this->whenLoaded('project', fn () => $this->project !== null ? [
                'id'   => $this->project->uuid,
                'name' => $this->project->name,
            ] : null),

            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($e) => [
                'id'        => $e->uuid,
                'name'      => trim("{$e->first_name} {$e->last_name}"),
                'job_title' => $e->job_title,
                'avatar_url' => $e->avatar ? Storage::disk('public')->url($e->avatar) : null,
            ])->all()),

            // Aggregates (present when the controller used withCount/withSum)
            'checklist_total'     => $this->when(isset($this->checklist_total), (int) ($this->checklist_total ?? 0)),
            'checklist_completed' => $this->when(isset($this->checklist_completed), (int) ($this->checklist_completed ?? 0)),
            'comments_count'      => $this->when(isset($this->comments_count), (int) ($this->comments_count ?? 0)),
            'total_minutes'       => $this->when(isset($this->total_minutes), (int) ($this->total_minutes ?? 0)),

            // Detail extras (present when loaded on show)
            'checklist_items' => $this->whenLoaded(
                'checklistItems',
                fn () => ChecklistItemResource::collection($this->checklistItems)->resolve(),
            ),

            'dependencies' => $this->whenLoaded('dependencies', fn () => $this->dependencies->map(fn ($dep) => [
                'id'          => $dep->uuid,
                'task_number' => $dep->task_number,
                'title'       => $dep->title,
                'status'      => $dep->status,
            ])->all()),

            'dependents' => $this->whenLoaded('dependents', fn () => $this->dependents->map(fn ($dep) => [
                'id'          => $dep->uuid,
                'task_number' => $dep->task_number,
                'title'       => $dep->title,
                'status'      => $dep->status,
            ])->all()),

            'time_entries' => $this->whenLoaded(
                'timeEntries',
                fn () => TimeEntryResource::collection($this->timeEntries)->resolve(),
            ),

            'is_watching'    => $this->when(
                $this->relationLoaded('watchers'),
                fn () => $this->watchers->contains(fn ($w) => $w->id === $request->user()?->id),
            ),
            'watchers_count' => $this->when(
                $this->relationLoaded('watchers'),
                fn () => $this->watchers->count(),
            ),

            'can_update' => $request->user()?->can('update', $this->resource) ?? false,
            'can_assign' => $request->user()?->can('manageAssignees', $this->resource) ?? false,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}