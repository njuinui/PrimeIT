<?php

namespace App\Domain\Tasks;

use App\Domain\Projects\RecalculateProjectProgress;
use App\Events\TaskStatusChanged;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\User;
use App\Models\Reminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTask
{
    public function __construct(private readonly RecalculateProjectProgress $recalculateProject) {}

    /**
     * @param array<string, mixed> $data validated attributes (may contain status)
     */
    public function __invoke(Task $task, array $data, User $actor): Task
    {
        $oldStatus = $task->status;
        $newStatus = array_key_exists('status', $data) ? (string) $data['status'] : $oldStatus;
        $originalDueDate = $task->due_date;

        // ── Dependency guard ──────────────────────────────────
        if (
            $newStatus !== $oldStatus
            && in_array($newStatus, ['in_progress', 'review', 'completed'], true)
        ) {
            $blocking = $task->dependencies()
                ->whereNotIn('tasks.status', ['completed', 'cancelled'])
                ->get(['tasks.title']);

            if ($blocking->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => ['This task is still blocked by: ' . $blocking->pluck('title')->implode(', ') . '.'],
                ]);
            }
        }

        // ── Completion stamping ────────────────────────────────
        if ($newStatus === 'completed') {
            $data['completed_at'] = $oldStatus === 'completed'
                ? ($task->completed_at ?? now())
                : now();
            $data['progress'] = 100;
        } elseif ($oldStatus === 'completed' && $newStatus !== 'completed') {
            $data['completed_at'] = null;
        }

        $task->update($data);
        // ── Reminder lifecycle (Phase 9) ──────────────────────────
        if (in_array($newStatus, ['completed', 'cancelled'], true)) {
            Reminder::cancelForRemindable($task);
        } elseif (
            array_key_exists('due_date', $data)
            && ($originalDueDate?->toDateString() ?? null) !== ($task->due_date?->toDateString() ?? null)
        ) {
            // Deadline moved — stale reminders die; the next scan recreates the right ones.
            Reminder::cancelForRemindable($task);
        }
        $task = $task->fresh();

        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            DB::table('task_assignees')
                ->where('task_id', $task->id)
                ->whereNull('completed_at')
                ->update(['completed_at' => now()]);
        } elseif ($oldStatus === 'completed' && $newStatus !== 'completed') {
            DB::table('task_assignees')
                ->where('task_id', $task->id)
                ->update(['completed_at' => null]);
        }

        // ── Event + project timeline + project progress ────────
        if ($newStatus !== $oldStatus) {
            event(new TaskStatusChanged($task, $oldStatus, $newStatus, $actor));

            if ($task->project_id !== null) {
                $project = Project::query()->find($task->project_id);

                $event = $newStatus === 'completed'
                    ? 'task_completed'
                    : ($oldStatus === 'completed' ? 'task_reopened' : 'task_status_changed');

                $description = match ($event) {
                    'task_completed'   => 'completed task "' . $task->title . '"',
                    'task_reopened'    => 'reopened task "' . $task->title . '"',
                    default            => sprintf(
                        'moved task "%s" from %s to %s',
                        $task->title,
                        str_replace('_', ' ', $oldStatus),
                        str_replace('_', ' ', $newStatus),
                    ),
                };

                ProjectActivity::record($project, $actor, $event, $description, $task);

                if ($newStatus === 'completed' || $oldStatus === 'completed') {
                    ($this->recalculateProject)($project);
                }
            }
        }

        return $task;
    }
}
