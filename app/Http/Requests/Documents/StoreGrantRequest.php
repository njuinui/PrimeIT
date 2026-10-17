<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'string',
                Rule::exists('users', 'uuid'),
            ],

            'ability' => [
                'required',
                'string',
                Rule::in([
                    'view',
                    'edit',
                ]),
            ],
        ];
    }
}