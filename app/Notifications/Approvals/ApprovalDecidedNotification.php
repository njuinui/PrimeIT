<?php

namespace App\Notifications\Approvals;

use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\User;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalRequest $request,
        public readonly User $actor,
        public readonly bool $approved,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::APPROVAL_DECIDED);
    }

    public function toArray(object $notifiable): array
    {
        $approvable = $this->request->approvable()->first();

        return [
            'category'    => Categories::APPROVAL_DECIDED,
            'title'       => $this->approved ? 'Request approved' : 'Request rejected',
            'body'        => sprintf(
                'Your request "%s" was %s by %s%s',
                $approvable instanceof Expense ? $approvable->title : 'an item',
                $this->approved ? 'approved' : 'rejected',
                $this->actor->name,
                $this->rejectionNote() !== null ? ' — "'.$this->rejectionNote().'"' : '',
            ),
            'request_id'  => $this->request->uuid,
            'expense_id'  => $approvable instanceof Expense ? $approvable->uuid : null,
            'amount'      => $approvable instanceof Expense ? (float) $approvable->amount : null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approvable = $this->request->approvable()->first();

        $url = $approvable instanceof Expense
            ? rtrim((string) config('app.frontend_url'), '/').'/expenses?expense='.$approvable->uuid
            : rtrim((string) config('app.frontend_url'), '/').'/approvals?request='.$this->request->uuid;

        return (new MailMessage)
            ->subject(($this->approved ? 'Approved: ' : 'Rejected: ').($approvable instanceof Expense ? $approvable->title : 'your request'))
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->actor->name.' '.$this->approved ? $this->actor->name.' approved your request.' : $this->actor->name.' rejected your request.')
            ->when($this->rejectionNote() !== null, fn (MailMessage $mail) => $mail
                ->line('Note: '.$this->rejectionNote()))
            ->action('View details', $url);
    }

    private function rejectionNote(): ?string
    {
        if ($this->approved) {
            $decision = $this->request->decisions()->where('decision', 'approved')->latest('id')->first();

            return $decision?->note;
        }

        $decision = $this->request->decisions()->where('decision', 'rejected')->latest('id')->first();

        return $decision?->note;
    }
}