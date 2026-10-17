<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Expense
 */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->uuid,
            'expense_number' => $this->expense_number,
            'title'         => $this->title,
            'description'   => $this->description,
            'status'        => $this->status,
            'amount'        => (float) $this->amount,
            'currency'      => $this->currency,
            'expense_date'  => $this->expense_date?->toDateString(),
            'decision_note' => $this->decision_note,
            'decided_at'    => $this->decided_at?->toISOString(),
            'paid_at'       => $this->paid_at?->toISOString(),
            'receipt_name'  => $this->receipt_name,
            'has_receipt'   => $this->receipt_path !== null,
            'created_at'    => $this->created_at?->toISOString(),

            'paid_amount' => $this->when(isset($this->paid_amount), (float) ($this->paid_amount ?? 0)),

            'category' => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->uuid,
                'name' => $this->category->name,
            ]),

            'project' => $this->whenLoaded('project', fn () => $this->project !== null ? [
                'id'   => $this->project->uuid,
                'name' => $this->project->name,
            ] : null),

            'department' => $this->whenLoaded('department', fn () => $this->department !== null ? [
                'id'   => $this->department->uuid,
                'name' => $this->department->name,
            ] : null),

            'submitter' => $this->whenLoaded('submitter', fn () => [
                'id'   => $this->submitter->uuid,
                'name' => $this->submitter->name,
            ]),

            'decider' => $this->whenLoaded('decider', fn () => $this->decider !== null ? [
                'id'   => $this->decider->uuid,
                'name' => $this->decider->name,
            ] : null),

            'payments' => $this->whenLoaded('payments', fn () => PaymentResource::collection($this->payments)->resolve()),

            'history' => $this->whenLoaded('history', fn () => ExpenseHistoryResource::collection($this->history)->resolve()),

            'can_update'         => $request->user()?->can('update', $this->resource) ?? false,
            'can_submit'         => $request->user()?->can('submit', $this->resource) ?? false,
            'can_decide'         => $request->user()?->can('decide', $this->resource) ?? false,
            'can_delete'         => $request->user()?->can('delete', $this->resource) ?? false,
            'can_record_payment' => $request->user()?->can('payments.manage')
                && $this->status === 'approved' ?? false,
        ];
    }
}