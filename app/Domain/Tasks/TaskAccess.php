<?php

namespace App\Domain\Tasks;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TaskAccess
{
    public function canView(User $user, Task $task): bool
    {
        return $user->can('tasks.view') && $task->isAccessibleBy($user);
    }

    public function canUpdate(User $user, Task $task): bool
    {
        if (! $user->can('tasks.update')) {
            return false;
        }

        if ($user->can('tasks.view.all') || $task->created_by === $user->id) {
            return true;
        }

        $employee = $user->employee()->first();

        if ($employee === null) {
            return false;
        }

        if ($task->assignees()->where('task_assignees.employee_id', $employee->id)->exists()) {
            return true;
        }

        return $this->isProjectStake($employee, $task->project_id);
    }

    public function canAssign(User $user, Task $task): bool
    {
        if (! $user->can('tasks.assign')) {
            return false;
        }

        if ($user->can('tasks.view.all') || $task->created_by === $user->id) {
            return true;
        }

        $employee = $user->employee()->first();

        return $employee !== null && $this->isProjectStake($employee, $task->project_id);
    }

    /**
     * May this user put these people on a task in this context?
     * Self-assignment is always allowed.
     *
     * @param array<int, int> $assigneeIds
     */
    public function canAssignInProject(User $user, ?int $projectId, array $assigneeIds): bool
    {
        $employee = $user->employee()->first();

        if ($employee !== null && $assigneeIds === [$employee->id]) {
            return true;
        }

        if (! $user->can('tasks.assign')) {
            return false;
        }

        if ($user->can('tasks.view.all') || $projectId === null) {
            return true;
        }

        return $employee !== null && $this->isProjectStake($employee, $projectId);
    }

    public function canDelete(User $user, Task $task): bool
    {
        if (! $user->can('tasks.delete')) {
            return false;
        }

        return $user->can('tasks.view.all') || $task->created_by === $user->id;
    }

    private function isProjectStake(Employee $employee, ?int $projectId): bool
    {
        if ($projectId === null) {
            return false;
        }

        $owns = Project::query()
            ->where('id', $projectId)
            ->where(function ($query) use ($employee) {
                $query->where('owner_id', $employee->id)
                    ->orWhere('project_manager_id', $employee->id);
            })
            ->exists();

        if ($owns) {
            return true;
        }

        return DB::table('project_members')
            ->where('project_id', $projectId)
            ->where('employee_id', $employee->id)
            ->where('role', 'manager')
            ->exists();
    }
}