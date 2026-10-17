<?php

namespace App\Domain\Tasks;

use App\Events\TaskAssigneeRemoved;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\User;

final class DetachAssignee
{
    public function __invoke(Task $task, Employee $employee, User $actor): void
    {
        $task->assignees()->detach($employee->id);

        event(new TaskAssigneeRemoved($task->fresh(), $employee, $actor));

        if ($task->project_id !== null) {
            ProjectActivity::record(
                Project::query()->find($task->project_id),
                $actor,
                'task_unassigned',
                'removed '.trim($employee->first_name.' '.$employee->last_name).' from task "'.$task->title.'"',
                $task,
            );
        }
    }
}