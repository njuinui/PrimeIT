<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskAssigneesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employees'   => ['present', 'array', 'max:10'],
            'employees.*' => ['string', Rule::exists('employees', 'uuid')],
        ];
    }
}