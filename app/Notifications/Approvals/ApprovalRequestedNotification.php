<?php

namespace App\Notifications\Approvals;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Expense;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalRequest $request,
        public readonly ApprovalStep $step,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::APPROVAL_REQUESTED);
    }

    public function toArray(object $notifiable): array
    {
        $approvable = $this->request->approvable()->first();

        return [
            'category'     => Categories::APPROVAL_REQUESTED,
            'title'        => 'Approval requested',
            'body'         => sprintf(
                '%s needs your approval on "%s" — step %d of %d (%s)',
                $this->request->requester()->first()?->name ?? 'Someone',
                $this->subjectTitle($approvable),
                $this->step->step_number,
                $this->request->steps()->count(),
                $this->step->name,
            ),
            'request_id'   => $this->request->uuid,
            'step_number'  => $this->step->step_number,
            'total_steps'  => $this->request->steps()->count(),
            'expense_id'   => $approvable instanceof Expense ? $approvable->uuid : null,
            'amount'       => $approvable instanceof Expense ? (float) $approvable->amount : null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/approvals?request='.$this->request->uuid;

        $approvable = $this->request->approvable()->first();

        return (new MailMessage)
            ->subject('Approval requested: '.$this->subjectTitle($approvable))
            ->greeting('Hi '.$notifiable->name.',')
            ->line(sprintf(
                '%s submitted something that needs your approval at step %d of %d — %s.',
                $this->request->requester()->first()?->name ?? 'Someone',
                $this->step->step_number,
                $this->request->steps()->count(),
                $this->step->name,
            ))
            ->when($approvable instanceof Expense, fn (MailMessage $mail) => $mail
                ->line('**'.$approvable->title.'** — '.number_format((float) $approvable->amount, 2).' '.$approvable->currency))
            ->action('Review request', $url);
    }

    private function subjectTitle($approvable): string
    {
        if ($approvable instanceof Expense) {
            return $approvable->title;
        }

        return 'an item';
    }
}