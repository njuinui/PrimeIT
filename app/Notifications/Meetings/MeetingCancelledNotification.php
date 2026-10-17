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

class MeetingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::MEETING_CANCELLED);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'   => Categories::MEETING_CANCELLED,
            'title'      => 'Meeting cancelled',
            'body'       => sprintf(
                '%s cancelled "%s" — was %s',
                $this->actor->name,
                $this->meeting->title,
                $this->meeting->starts_at->format('D, M j, Y \a\t H:i'),
            ),
            'meeting_id' => $this->meeting->uuid,
            'starts_at'  => $this->meeting->starts_at->toISOString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cancelled: '.$this->meeting->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->actor->name.' cancelled the meeting "'.$this->meeting->title.'".')
            ->line('It was scheduled for '.$this->meeting->starts_at->format('D, M j, Y \a\t H:i').'.');
    }
}