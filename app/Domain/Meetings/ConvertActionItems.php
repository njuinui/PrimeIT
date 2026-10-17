<?php

namespace App\Domain\Meetings;

use App\Domain\Tasks\CreateTask;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\User;

final class ConvertActionItems
{
    public function __construct(private readonly CreateTask $createTask) {}

    /**
     * Turns every un-converted action item into a real task (assigned to
     * the chosen employee) and stamps the task uuid back into the minutes.
     *
     * @return array{minutes: MeetingMinutes, created: array<int, array{title: string, task_id: string}>}
     */
    public function __invoke(Meeting $meeting, User $actor): array
    {
        $minutes = $meeting->minutes()->first();

        abort_if($minutes === null, 404, 'No minutes recorded for this meeting.');

        $items     = $minutes->action_items ?? [];
        $created   = [];

        foreach ($items as $index => $item) {
            if (($item['task_id'] ?? null) !== null) {
                continue;
            }

            $assigneeIds = [];

            if (($item['assignee_id'] ?? null) !== null) {
                $employeeId = Employee::query()->where('uuid', $item['assignee_id'])->value('id');

                if ($employeeId !== null) {
                    $assigneeIds = [$employeeId];
                }
            }

            $task = ($this->createTask)([
                'title'    => (string) $item['title'],
                'status'   => 'todo',
                'priority' => 'medium',
                'due_date' => $item['due_date'] ?? null,
            ], $assigneeIds, $actor);

            $items[$index]['task_id'] = $task->uuid;
            $created[] = ['title' => $task->title, 'task_id' => $task->uuid];
        }

        $minutes->forceFill(['action_items' => array_values($items)])->save();

        return ['minutes' => $minutes->fresh(), 'created' => $created];
    }
}