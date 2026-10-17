<?php

namespace App\Listeners;

use App\Domain\Notifications\TaskNotificationRecipients;
use App\Events\TaskStatusChanged;
use App\Models\User;
use App\Notifications\Tasks\TaskStatusChangedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskStatusChangedNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly TaskNotificationRecipients $recipients) {}

    public function handle(TaskStatusChanged $event): void
    {
        $event->task->loadMissing('project');

        $userIds = $this->recipients
            ->forTask($event->task)
            ->reject(fn ($id) => $id === $event->actor->id);

        if ($userIds->isEmpty()) {
            return;
        }

        User::query()->whereIn('id', $userIds->all())->get()->each(
            fn (User $user) => $user->notify(
                new TaskStatusChangedNotification($event->task, $event->oldStatus, $event->newStatus, $event->actor),
            ),
        );
    }
}