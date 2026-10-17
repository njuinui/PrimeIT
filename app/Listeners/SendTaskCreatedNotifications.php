<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Notifications\Tasks\TaskAssignedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskCreatedNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(TaskCreated $event): void
    {
        $event->task->loadMissing('project');

        $event->task->assignees()->get()
            ->reject(fn ($employee) => $employee->user_id === null || $employee->user_id === $event->actor->id)
            ->each(function ($employee) use ($event): void {
                $employee->user()->first()?->notify(
                    new TaskAssignedNotification($event->task, $event->actor),
                );
            });
    }
}