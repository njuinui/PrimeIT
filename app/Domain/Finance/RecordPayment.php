<?php

namespace App\Domain\Finance;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Finance\ExpensePaidNotification;
use Illuminate\Support\Facades\DB;

final class RecordPayment
{
    /**
     * Creates the payment, flips the expense to paid when fully covered,
     * writes history and notifies the submitter. Caller validates the
     * amount against the remaining balance.
     *
     * @param array<string, mixed> $data validated payment attributes
     */
    public function __invoke(Expense $expense, array $data, User $recorder): Payment
    {
        $payment = DB::transaction(function () use ($expense, $data, $recorder): Payment {
            $payment = $expense->payments()->create([
                ...$data,
                'user_id'  => $recorder->id,
                'currency' => $expense->currency,
            ]);

            $wasPaid = $expense->status === 'paid';

            if (! $wasPaid && $expense->fresh()->paidAmount() >= (float) $expense->amount) {
                $expense->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

                $expense->recordHistory($recorder, 'paid', 'approved', 'paid', sprintf(
                    '%s %s paid via %s',
                    number_format((float) $payment->amount, 2),
                    $payment->currency,
                    str_replace('_', ' ', (string) $payment->method),
                ));
            }

            return $payment;
        });

        if ($expense->fresh()->status === 'paid'
            && $expense->submitter()->first() !== null
            && $expense->submitter()->first()->id !== $recorder->id) {
            $expense->submitter()->first()->notify(
                new ExpensePaidNotification($expense->fresh(), $payment->loadMissing('expense')),
            );
        }

        return $payment;
    }
}