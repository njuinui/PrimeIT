<?php

namespace App\Listeners;

use App\Events\TaskAssigneesAdded;
use App\Notifications\Tasks\TaskAssignedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskAssigneesAddedNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(TaskAssigneesAdded $event): void
    {
        $event->task->loadMissing('project');

        $event->employees
            ->reject(fn ($employee) => $employee->user_id === null || $employee->user_id === $event->actor->id)
            ->each(function ($employee) use ($event): void {
                $employee->user()->first()?->notify(
                    new TaskAssignedNotification($event->task, $event->actor),
                );
            });
    }
}