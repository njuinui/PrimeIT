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

class ApprovalEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalRequest $request,
        public readonly ApprovalStep $step,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::APPROVAL_ESCALATED);
    }

    public function toArray(object $notifiable): array
    {
        $approvable = $this->request->approvable()->first();
        $waitingDays = (int) abs(now()->diffInDays($this->step->created_at, false));

        return [
            'category'    => Categories::APPROVAL_ESCALATED,
            'title'       => 'Approval escalated to you',
            'body'        => sprintf(
                'A "%s" approval has been waiting %d days — please check in with %s',
                $approvable instanceof Expense ? $approvable->title : 'request',
                max(1, $waitingDays),
                $this->request->requester()->first()?->name ?? 'the requester',
            ),
            'request_id'  => $this->request->uuid,
            'step_number' => $this->step->step_number,
            'total_steps' => $this->request->steps()->count(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/approvals?request='.$this->request->uuid;

        return (new MailMessage)
            ->subject('Approval escalated to you')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('An approval in your team has been pending too long and has been escalated to you.')
            ->action('Review request', $url);
    }
}