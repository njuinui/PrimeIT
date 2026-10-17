<?php

namespace App\Notifications\Documents;

use App\Models\Document;
use App\Models\User;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentSharedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Document $document,
        public readonly User $actor,
        public readonly string $ability,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::DOCUMENT_SHARED);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'    => Categories::DOCUMENT_SHARED,
            'title'       => 'Document shared with you',
            'body'        => sprintf(
                '%s shared "%s" with you (%s access)',
                $this->actor->name,
                $this->document->title,
                $this->ability === 'edit' ? 'edit' : 'view',
            ),
            'document_id' => $this->document->uuid,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/documents?document='.$this->document->uuid;

        return (new MailMessage)
            ->subject('Shared with you: '.$this->document->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->actor->name.' shared a document with you.')
            ->line('**'.$this->document->title.'** ('.$this->document->file_name.')')
            ->line(sprintf(
                'You have %s access.',
                $this->ability === 'edit' ? 'edit (upload new versions, rename, share)' : 'view & download',
            ))
            ->action('Open document', $url);
    }
}