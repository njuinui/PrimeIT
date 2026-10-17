<?php

namespace App\Domain\Notifications;

use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TaskNotificationRecipients
{
    /**
     * Everyone with a stake in a task: assignees' accounts, watchers,
     * and the creator. Explicit queries — safe under strict lazy-loading.
     *
     * @return Collection<int, int>
     */
    public function forTask(Task $task): Collection
    {
        $assigneeUserIds = DB::table('task_assignees')
            ->join('employees', 'employees.id', '=', 'task_assignees.employee_id')
            ->where('task_assignees.task_id', $task->id)
            ->whereNull('employees.deleted_at')
            ->pluck('employees.user_id');

        $watcherUserIds = DB::table('task_watchers')
            ->where('task_id', $task->id)
            ->pluck('user_id');

        return $assigneeUserIds
            ->merge($watcherUserIds)
            ->push($task->created_by)
            ->filter()
            ->unique()
            ->values();
    }
}