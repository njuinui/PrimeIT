<?php

namespace App\Notifications\Finance;

use App\Models\Expense;
use App\Models\User;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpenseSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Expense $expense,
        public readonly User $submitter,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::EXPENSE_SUBMITTED);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'     => Categories::EXPENSE_SUBMITTED,
            'title'        => 'Expense awaiting approval',
            'body'         => sprintf(
                '%s submitted "%s" — %s %s',
                $this->submitter->name,
                $this->expense->title,
                number_format((float) $this->expense->amount, 0),
                $this->expense->currency,
            ),
            'expense_id'   => $this->expense->uuid,
            'expense_no'   => $this->expense->expense_number,
            'amount'       => (float) $this->expense->amount,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/expenses?expense='.$this->expense->uuid;

        return (new MailMessage)
            ->subject('Expense awaiting approval: '.$this->expense->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->submitter->name.' submitted an expense for approval.')
            ->line('**'.$this->expense->title.'**')
            ->line(sprintf(
                'Amount: %s %s · Date: %s',
                number_format((float) $this->expense->amount, 2),
                $this->expense->currency,
                $this->expense->expense_date->format('D, M j, Y'),
            ))
            ->action('Review expense', $url);
    }
}