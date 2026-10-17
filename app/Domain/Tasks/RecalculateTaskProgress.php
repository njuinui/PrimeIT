<?php

namespace App\Domain\Tasks;

use App\Models\Task;

/**
 * When a checklist exists, task progress is the completed-item ratio.
 * Completed tasks are pinned to 100; tasks without checklists keep
 * their manually set progress.
 */
final class RecalculateTaskProgress
{
    public function __invoke(Task $task): Task
    {
        if ($task->status === 'completed') {
            $task->forceFill(['progress' => 100])->save();

            return $task->fresh();
        }

        $total = $task->checklistItems()->count();

        if ($total > 0) {
            $completed = $task->checklistItems()->where('is_completed', true)->count();

            $task->forceFill(['progress' => (int) round($completed / $total * 100)])->save();
        }

        return $task->fresh();
    }
}