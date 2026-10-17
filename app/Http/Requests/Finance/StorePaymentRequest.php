<?php

namespace App\Http\Requests\Finance;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'    => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'method'    => ['required', 'string', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note'      => ['nullable', 'string', 'max:500'],
            'paid_at'   => ['nullable', 'date'],
        ];
    }
}