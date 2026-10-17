<?php

namespace App\Notifications\Documents;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentNewVersionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Document $document,
        public readonly DocumentVersion $version,
        public readonly User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::DOCUMENT_NEW_VERSION);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'    => Categories::DOCUMENT_NEW_VERSION,
            'title'       => 'New version available',
            'body'        => sprintf(
                '%s uploaded v%d of "%s"',
                $this->actor->name,
                $this->version->version_number,
                $this->document->title,
            ),
            'document_id' => $this->document->uuid,
            'version'     => $this->version->version_number,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/documents?document='.$this->document->uuid;

        return (new MailMessage)
            ->subject('Updated: '.$this->document->title.' (v'.$this->version->version_number.')')
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->actor->name.' uploaded version '.$this->version->version_number.' of "'.$this->document->title.'".')
            ->when($this->version->note !== null, fn (MailMessage $mail) => $mail
                ->line('Note: '.$this->version->note))
            ->action('Open document', $url);
    }
}