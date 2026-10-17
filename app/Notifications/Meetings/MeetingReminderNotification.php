<?php

namespace App\Notifications\Meetings;

use App\Models\Meeting;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly string $type, // meeting_upcoming_24h | meeting_upcoming_15m
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::MEETING_REMINDER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'   => Categories::MEETING_REMINDER,
            'title'      => $this->type === 'meeting_upcoming_15m' ? 'Meeting starts soon' : 'Meeting in 24 hours',
            'body'       => sprintf(
                '"%s" starts %s%s',
                $this->meeting->title,
                $this->meeting->starts_at->format('D, M j, Y \a\t H:i'),
                $this->meeting->location !== null ? ' · '.$this->meeting->location : '',
            ),
            'meeting_id' => $this->meeting->uuid,
            'starts_at'  => $this->meeting->starts_at->toISOString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/meetings?meeting='.$this->meeting->uuid;

        return (new MailMessage)
            ->subject(($this->type === 'meeting_upcoming_15m' ? 'Starting soon: ' : 'Reminder: ').$this->meeting->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line('**'.$this->meeting->title.'**')
            ->line('Starts: '.$this->meeting->starts_at->format('D, M j, Y \a\t H:i'))
            ->when($this->meeting->location !== null, fn (MailMessage $mail) => $mail
                ->line('Where: '.$this->meeting->location))
            ->when($this->meeting->video_link !== null, fn (MailMessage $mail) => $mail
                ->line('Join: '.$this->meeting->video_link))
            ->action('View meeting', $url);
    }
}