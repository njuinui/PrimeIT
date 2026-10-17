<?php

namespace App\Domain\Reminders;

use App\Models\Employee;
use App\Models\Reminder;
use App\Models\Task;
use Illuminate\Support\Carbon;

final class ScanTaskReminders
{
    public const TYPE_DUE_SOON = 'due_soon';
    public const TYPE_ESCALATION = 'escalation';

    /**
     * @return array{created: int, skipped: int}
     */
    public function __invoke(): array
    {
        $stats = ['created' => 0, 'skipped' => 0];

        Task::query()
            ->with(['assignees'])
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->chunkById(200, function ($tasks) use (&$stats): void {
                foreach ($tasks as $task) {
                    if ($this->scanTask($task)) {
                        $stats['created']++;
                    } else {
                        $stats['skipped']++;
                    }
                }
            });

        return $stats;
    }

    private function scanTask(Task $task): bool
    {
        if ($task->trashed() || $task->due_date === null) {
            return false;
        }

        $dueDate = Carbon::parse($task->due_date);
        $created = false;

        $dueSoonAt = $dueDate->copy()->subDays(2);

        if ($dueSoonAt->isFuture()) {
            foreach ($task->assignees as $employee) {
                if (! $this->isEligible($employee)) {
                    continue;
                }

                $created = $this->createReminderIfMissing(
                    task: $task,
                    userId: $employee->user_id,
                    type: self::TYPE_DUE_SOON,
                    scheduledFor: $dueSoonAt,
                ) || $created;
            }
        }

        if ($dueDate->isPast()) {
            foreach ($task->assignees as $employee) {
                if (! $this->isEligible($employee)) {
                    continue;
                }

                $created = $this->createReminderIfMissing(
                    task: $task,
                    userId: $employee->user_id,
                    type: self::TYPE_ESCALATION,
                    scheduledFor: $dueDate,
                ) || $created;
            }
        }

        return $created;
    }

    /**
     * An employee is eligible for reminders only when they have a linked
     * user account and are currently active.
     */
    private function isEligible(Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->isActive();
    }

    private function createReminderIfMissing(
        Task $task,
        int $userId,
        string $type,
        Carbon $scheduledFor,
    ): bool {
        $exists = Reminder::query()
            ->where('user_id', $userId)
            ->where('remindable_type', Task::class)
            ->where('remindable_id', $task->id)
            ->where('type', $type)
            ->whereIn('status', [
                Reminder::STATUS_PENDING,
                Reminder::STATUS_SENT,
            ])
            ->exists();

        if ($exists) {
            return false;
        }

        Reminder::query()->create([
            'user_id'         => $userId,
            'remindable_type' => Task::class,
            'remindable_id'   => $task->id,
            'type'            => $type,
            'scheduled_for'   => $scheduledFor,
            'status'          => Reminder::STATUS_PENDING,
        ]);

        return true;
    }
}