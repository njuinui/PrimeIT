<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskCommentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
        public readonly TaskComment $comment,
        public readonly User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::TASK_COMMENTED);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'     => Categories::TASK_COMMENTED,
            'title'        => 'New comment on a task you follow',
            'body'         => sprintf(
                '%s commented on "%s": "%s"',
                $this->actor->name,
                $this->task->title,
                Str::limit($this->comment->body, 80),
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
            ->subject('New comment on: '.$this->task->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->actor->name.' commented:')
            ->line('> '.Str::limit($this->comment->body, 120))
            ->action('View task', $url);
    }
}