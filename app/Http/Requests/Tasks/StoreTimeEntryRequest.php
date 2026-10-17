<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'minutes'   => ['required', 'integer', 'min:1', 'max:1440'],
            'note'      => ['nullable', 'string', 'max:500'],
            'worked_at' => ['nullable', 'date'],
        ];
    }
}