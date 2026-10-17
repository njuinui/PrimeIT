<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMembersRequest extends FormRequest
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
            'role'        => ['required', 'string', Rule::in(['manager', 'member', 'viewer'])],
        ];
    }
}