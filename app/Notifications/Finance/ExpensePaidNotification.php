<?php

namespace App\Notifications\Finance;

use App\Models\Expense;
use App\Models\Payment;
use App\Support\Notifications\Categories;
use App\Support\Notifications\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpensePaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Expense $expense,
        public readonly Payment $payment,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationSettings::class)
            ->channelsFor($notifiable, Categories::EXPENSE_PAID);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category'     => Categories::EXPENSE_PAID,
            'title'        => 'Expense paid',
            'body'         => sprintf(
                '"%s" has been settled — %s %s via %s',
                $this->expense->title,
                number_format((float) $this->payment->amount, 0),
                $this->payment->currency,
                str_replace('_', ' ', (string) $this->payment->method),
            ),
            'expense_id'   => $this->expense->uuid,
            'expense_no'   => $this->expense->expense_number,
            'amount'       => (float) $this->payment->amount,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/expenses?expense='.$this->expense->uuid;

        return (new MailMessage)
            ->subject('Expense paid: '.$this->expense->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your expense has been settled.')
            ->line('**'.$this->expense->title.'**')
            ->line(sprintf(
                'Paid: %s %s via %s%s',
                number_format((float) $this->payment->amount, 2),
                $this->payment->currency,
                str_replace('_', ' ', (string) $this->payment->method),
                $this->payment->reference !== null ? ' (ref: '.$this->payment->reference.')' : '',
            ))
            ->action('View expense', $url);
    }
}