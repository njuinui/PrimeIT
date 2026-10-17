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

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
        public readonly User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::TASK_ASSIGNED);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'     => Categories::TASK_ASSIGNED,
            'title'        => 'New task assignment',
            'body'         => sprintf('%s assigned you "%s"', $this->actor->name, $this->task->title),
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
            ->subject('New task assigned: '.$this->task->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->actor->name.' assigned you a task in PrimeIT Workspace.')
            ->line('**'.$this->task->title.'**')
            ->when($this->task->due_date !== null, fn (MailMessage $mail) => $mail
                ->line('Due: '.$this->task->due_date->format('D, M j, Y')))
            ->action('View task', $url)
            ->line('You can change how you receive these notifications from the notification center.');
    }
}