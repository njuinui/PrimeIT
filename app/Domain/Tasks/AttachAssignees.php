<?php

namespace App\Domain\Tasks;

use App\Events\TaskAssigneesAdded;
use App\Events\TaskBoardUpdated;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

final class AttachAssignees
{
    /**
     * Attaches only the employees not already on the task, auto-watches
     * their accounts and fires the assignment event.
     *
     * @param  array<int, int> $employeeIds
     * @return Collection<int, Employee> the newly attached employees
     */
    public function __invoke(Task $task, array $employeeIds, User $actor): Collection
    {
        $existing = $task->assignees()->pluck('employees.id')->all();

        $newIds = array_values(array_diff($employeeIds, $existing));

        if ($newIds === []) {
            return new Collection();
        }

        $task->assignees()->attach(collect($newIds)->mapWithKeys(
            fn (int $id) => [$id => ['assigned_by' => $actor->id, 'assigned_at' => now()]]
        )->all());

        $employees = Employee::query()->whereIn('id', $newIds)->get();

        $task->watchers()->syncWithoutDetaching(
            $employees->pluck('user_id')->filter()->unique()->values()->all(),
        );

        event(new TaskAssigneesAdded($task->fresh(), $employees, $actor));

                broadcast(new TaskBoardUpdated('task.assignees_changed', $task->uuid, $task->task_number, $task->status));

        if ($task->project_id !== null) {
            $names = $employees->take(3)
                ->map(fn (Employee $e) => trim($e->first_name.' '.$e->last_name))
                ->implode(', ');
            $suffix = $employees->count() > 3 ? sprintf(' and %d more', $employees->count() - 3) : '';

            ProjectActivity::record(
                Project::query()->find($task->project_id),
                $actor,
                'task_assigned',
                'assigned '.$names.$suffix.' to task "'.$task->title.'"',
                $task,
            );
        }

        return $employees;
    }
}