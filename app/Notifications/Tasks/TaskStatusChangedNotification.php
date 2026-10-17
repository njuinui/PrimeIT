<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::TASK_STATUS_CHANGED);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'     => Categories::TASK_STATUS_CHANGED,
            'title'        => 'Task status updated',
            'body'         => sprintf(
                '%s moved "%s" from %s to %s',
                $this->actor->name,
                $this->task->title,
                str_replace('_', ' ', $this->oldStatus),
                str_replace('_', ' ', $this->newStatus),
            ),
            'task_id'      => $this->task->uuid,
            'task_number'  => $this->task->task_number,
            'project_name' => $this->task->relationLoaded('project') ? $this->task->project?->name : null,
            'due_date'     => $this->task->due_date?->toDateString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/tasks?task='.$this->task->uuid;

        return (new MailMessage)
            ->subject('Task updated: '.$this->task->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line(sprintf(
                '%s moved **%s** from "%s" to "%s".',
                $this->actor->name,
                $this->task->title,
                str_replace('_', ' ', $this->oldStatus),
                str_replace('_', ' ', $this->newStatus),
            ))
            ->action('View task', $url);
    }
}