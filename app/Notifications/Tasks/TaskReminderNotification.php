<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
        public readonly string $type,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::TASK_REMINDER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'     => Categories::TASK_REMINDER,
            'title'        => $this->title(),
            'body'         => $this->body(),
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
            ->subject('Reminder: '.$this->title().' — '.$this->task->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->body().'.')
            ->line('**'.$this->task->title.'**')
            ->when($this->task->project !== null, fn (MailMessage $mail) => $mail
                ->line('Project: '.$this->task->project->name))
            ->action('View task', $url)
            ->line('You can adjust deadline reminders from the notification center.');
    }

    private function title(): string
    {
        return match ($this->type) {
            'task_due_7d'    => 'Task due in 7 days',
            'task_due_3d'    => 'Task due in 3 days',
            'task_due_2d'    => 'Task due in 2 days',
            'task_due_1d'    => 'Task due tomorrow',
            'task_due_today' => 'Task due today',
            'task_overdue'   => 'Task overdue',
            default          => 'Task deadline reminder',
        };
    }

    private function body(): string
    {
        $due = $this->task->due_date?->format('D, M j, Y') ?? 'no due date';

        if ($this->type === 'task_overdue') {
            $days = abs((int) now()->startOfDay()->diffInDays(
                $this->task->due_date->copy()->startOfDay(), false,
            ));

            return sprintf(
                '"%s" was due %s (%d %s overdue)',
                $this->task->title, $due, $days, $days === 1 ? 'day' : 'days',
            );
        }

        return sprintf('"%s" is due %s', $this->task->title, $due);
    }
}