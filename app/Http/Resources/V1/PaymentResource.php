<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->uuid,
            'amount'     => (float) $this->amount,
            'currency'   => $this->currency,
            'method'     => $this->method,
            'reference'  => $this->reference,
            'note'       => $this->note,
            'paid_at'    => $this->paid_at?->toDateString(),
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'created_at' => $this->created_at?->toISOString(),

            'can_delete' => $request->user()?->id === $this->user_id
                || $request->user()?->can('finance.view.all'),
        ];
    }
}