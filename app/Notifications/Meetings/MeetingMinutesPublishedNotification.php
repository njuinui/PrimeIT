<?php

namespace App\Notifications\Meetings;

use App\Models\Meeting;
use App\Models\User;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingMinutesPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly User $actor,
        public readonly int $actionItemCount,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::MEETING_MINUTES);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'   => Categories::MEETING_MINUTES,
            'title'      => 'Minutes published',
            'body'       => sprintf(
                'Minutes for "%s" are available%s',
                $this->meeting->title,
                $this->actionItemCount > 0
                    ? sprintf(' — %d action item(s) to review', $this->actionItemCount)
                    : '',
            ),
            'meeting_id' => $this->meeting->uuid,
            'starts_at'  => $this->meeting->starts_at->toISOString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/meetings?meeting='.$this->meeting->uuid;

        return (new MailMessage)
            ->subject('Minutes published: '.$this->meeting->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->actor->name.' published the minutes for "'.$this->meeting->title.'".')
            ->when($this->actionItemCount > 0, fn (MailMessage $mail) => $mail
                ->line(sprintf('%d action item(s) were captured — check whether any are yours.', $this->actionItemCount)))
            ->action('View minutes', $url);
    }
}