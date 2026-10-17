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

class ExpenseDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Expense $expense,
        public readonly User $decider,
        public readonly bool $approved,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::EXPENSE_DECIDED);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'     => Categories::EXPENSE_DECIDED,
            'title'        => $this->approved ? 'Expense approved' : 'Expense rejected',
            'body'         => sprintf(
                '%s %s "%s" (%s %s)%s',
                $this->decider->name,
                $this->approved ? 'approved' : 'rejected',
                $this->expense->title,
                number_format((float) $this->expense->amount, 0),
                $this->expense->currency,
                $this->expense->decision_note !== null ? ' — "'.$this->expense->decision_note.'"' : '',
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
            ->subject(($this->approved ? 'Expense approved: ' : 'Expense rejected: ').$this->expense->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($this->decider->name.($this->approved ? ' approved' : ' rejected').' your expense.')
            ->line('**'.$this->expense->title.'** — '.number_format((float) $this->expense->amount, 2).' '.$this->expense->currency)
            ->when($this->expense->decision_note !== null, fn (MailMessage $mail) => $mail
                ->line('Note: '.$this->expense->decision_note))
            ->action('View expense', $url);
    }
}