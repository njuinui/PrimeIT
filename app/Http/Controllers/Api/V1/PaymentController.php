<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\RecordPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Http\Resources\V1\ExpenseResource;
use App\Http\Resources\V1\PaymentResource;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly RecordPayment $recordPayment) {}

    public function store(StorePaymentRequest $request, Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);
        abort_unless($request->user()->can('payments.manage'), 403);

        if ($expense->status !== 'approved') {
            return $this->error('Payments can only be recorded against approved expenses.', 409);
        }

        $amount = (float) $request->validated('amount');

        if ($amount > $expense->remainingAmount() + 0.001) {
            return $this->error(sprintf(
                'Payment exceeds the remaining balance (%s %s).',
                number_format($expense->remainingAmount(), 2),
                $expense->currency,
            ), 422);
        }

        $payment = ($this->recordPayment)(
            $expense,
            collect($request->validated())->except('amount')->all() + ['amount' => $amount],
            $request->user(),
        );

        return $this->success([
            'payment' => new PaymentResource($payment->loadMissing('recorder')),
            'expense' => new ExpenseResource($expense->fresh()->loadMissing(
                ['category', 'project', 'department', 'submitter', 'decider'],
            )),
        ], 'Payment recorded.', 201);
    }

    public function destroy(Request $request, Expense $expense, Payment $payment): JsonResponse
    {
        $this->authorize('view', $expense);
        abort_unless($request->user()->can('payments.manage'), 403);
        abort_unless($payment->expense_id === $expense->id, 404);

        if ($payment->user_id !== $request->user()->id && ! $request->user()->can('finance.view.all')) {
            return $this->error('You can only remove payments you recorded.', 403);
        }

        $wasPaid = $expense->status === 'paid';

        $payment->delete();

        $expense = $expense->fresh();

        if ($wasPaid && $expense->paidAmount() < (float) $expense->amount) {
            $expense->forceFill(['status' => 'approved', 'paid_at' => null])->save();
        }

        $expense->recordHistory(
            $request->user(),
            'payment_removed',
            $wasPaid ? 'paid' : 'approved',
            $expense->fresh()->status,
            sprintf('Removed a payment of %s %s', number_format((float) $payment->amount, 2), $payment->currency),
        );

        return $this->success(
            new ExpenseResource($expense->fresh()->loadMissing(
                ['category', 'project', 'department', 'submitter', 'decider', 'payments.recorder'],
            )),
            'Payment removed.',
        );
    }
}