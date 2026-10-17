<?php

namespace App\Domain\Reminders;

use App\Models\Meeting;
use App\Models\Reminder;
use App\Models\Task;
use App\Notifications\Meetings\MeetingReminderNotification;
use App\Notifications\Tasks\TaskOverdueEscalatedNotification;
use App\Notifications\Tasks\TaskReminderNotification;
use Throwable;

final class SendDueReminders
{
    /**
     * @return array{sent: int, cancelled: int, failed: int}
     */
    public function __invoke(): array
    {
        $stats = ['sent' => 0, 'cancelled' => 0, 'failed' => 0];

        Reminder::query()
            ->due()
            ->with(['user', 'remindable'])
            ->orderBy('scheduled_for')
            ->chunkById(200, function ($reminders) use (&$stats): void {
                foreach ($reminders as $reminder) {
                    $remindable = $reminder->remindable;

                    $notification = $this->notificationFor($reminder, $remindable);

                    // Unknown / finished / deleted subjects never fire.
                    if ($notification === null) {
                        $this->transition($reminder, Reminder::STATUS_CANCELLED);
                        $stats['cancelled']++;
                        continue;
                    }

                    $user = $reminder->user;

                    if ($user === null || ! $user->isActive()) {
                        $this->transition($reminder, Reminder::STATUS_CANCELLED);
                        $stats['cancelled']++;
                        continue;
                    }

                    try {
                        $user->notify($notification);

                        $this->transition($reminder, Reminder::STATUS_SENT, ['sent_at' => now()]);
                        $stats['sent']++;
                    } catch (Throwable $exception) {
                        $reminder->forceFill([
                            'status'         => Reminder::STATUS_FAILED,
                            'failed_at'      => now(),
                            'failure_reason' => mb_substr($exception->getMessage(), 0, 500),
                        ])->save();
                        $stats['failed']++;
                    }
                }
            });

        return $stats;
    }

    private function notificationFor(Reminder $reminder, $remindable): ?object
    {
        if ($remindable instanceof Task) {
            if ($remindable->trashed() || in_array($remindable->status, ['completed', 'cancelled'], true)) {
                return null;
            }

            $remindable->loadMissing('project', 'assignees');

            return $reminder->type === ScanTaskReminders::TYPE_ESCALATION
                ? new TaskOverdueEscalatedNotification($remindable)
                : new TaskReminderNotification($remindable, $reminder->type);
        }

        if ($remindable instanceof Meeting) {
            if ($remindable->trashed() || $remindable->status !== 'scheduled') {
                return null;
            }

            return new MeetingReminderNotification($remindable, $reminder->type);
        }

        return null;
    }

    private function transition(Reminder $reminder, string $status, array $extra = []): void
    {
        $reminder->forceFill(['status' => $status, ...$extra])->save();
    }
}