<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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