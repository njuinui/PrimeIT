<?php

namespace App\Http\Requests\Company;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('manager_id')) {
            $this->merge([
                'manager_id' => $this->input('manager_id') === '' || $this->input('manager_id') === null
                    ? null
                    : Employee::query()->where('uuid', $this->input('manager_id'))->value('id') ?? 'invalid',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'code'        => ['required', 'string', 'alpha_dash', 'max:20', Rule::unique('departments', 'code')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
            'manager_id'  => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ];
    }
}