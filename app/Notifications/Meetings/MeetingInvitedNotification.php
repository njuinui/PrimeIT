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

class MeetingInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly User $actor,
        public readonly string $attendance = 'required',
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::MEETING_INVITED);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'    => Categories::MEETING_INVITED,
            'title'       => 'Meeting invitation',
            'body'        => sprintf(
                '%s invited you to "%s" — %s%s',
                $this->actor->name,
                $this->meeting->title,
                $this->meeting->starts_at->format('D, M j, Y \a\t H:i'),
                $this->attendance === 'optional' ? ' (optional)' : '',
            ),
            'meeting_id'  => $this->meeting->uuid,
            'starts_at'   => $this->meeting->starts_at->toISOString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/meetings?meeting='.$this->meeting->uuid;

        return (new MailMessage)
            ->subject('Invitation: '.$this->meeting->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->actor->name.' invited you to a meeting.')
            ->line('**'.$this->meeting->title.'**')
            ->line('When: '.$this->meeting->starts_at->format('D, M j, Y \a\t H:i')
                .' – '.$this->meeting->ends_at->format('H:i'))
            ->when($this->meeting->location !== null, fn (MailMessage $mail) => $mail
                ->line('Where: '.$this->meeting->location))
            ->when($this->meeting->video_link !== null, fn (MailMessage $mail) => $mail
                ->line('Join: '.$this->meeting->video_link))
            ->action('View meeting & respond', $url);
    }
}