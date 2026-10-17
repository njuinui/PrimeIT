<?php

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employees'   => ['present', 'array', 'max:50'],
            'employees.*' => ['string', Rule::exists('employees', 'uuid')],
            'attendance'  => ['required', 'string', Rule::in(['required', 'optional'])],
        ];
    }
}