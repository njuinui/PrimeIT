<?php

namespace App\Notifications\Tasks;

use App\Models\Task;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskOverdueEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Task $task) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::TASK_REMINDER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'     => Categories::TASK_REMINDER,
            'title'        => 'Overdue task on your team',
            'body'         => sprintf(
                '%s is %s — please check in with %s',
                $this->taskTitle(),
                $this->overduePhrase(),
                $this->assigneeNames(),
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
            ->subject('Overdue task on your team: '.$this->task->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->taskTitle().' '.$this->overduePhrase().'.')
            ->line('Assigned to: '.$this->assigneeNames())
            ->action('View task', $url)
            ->line('This is an automated escalation for overdue tasks in your reporting line.');
    }

    private function taskTitle(): string
    {
        return sprintf('"%s"', $this->task->title);
    }

    private function overduePhrase(): string
    {
        $days = abs((int) now()->startOfDay()->diffInDays(
            $this->task->due_date->copy()->startOfDay(), false,
        ));

        return sprintf('%d %s overdue', $days, $days === 1 ? 'day' : 'days');
    }

    private function assigneeNames(): string
    {
        if (! $this->task->relationLoaded('assignees') || $this->task->assignees->isEmpty()) {
            return 'your team';
        }

        $names = $this->task->assignees
            ->take(2)
            ->map(fn ($employee) => trim($employee->first_name.' '.$employee->last_name))
            ->implode(', ');

        if ($this->task->assignees->count() > 2) {
            $names .= sprintf(' and %d more', $this->task->assignees->count() - 2);
        }

        return $names;
    }
}