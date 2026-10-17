<?php

namespace App\Http\Requests\Company;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'department_id' => Department::query()->where('uuid', $this->input('department_id'))->value('id') ?? 'invalid',
            'leader_id'     => $this->has('leader_id') && $this->input('leader_id') !== null && $this->input('leader_id') !== ''
                ? Employee::query()->where('uuid', $this->input('leader_id'))->value('id') ?? 'invalid'
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'name'          => ['required', 'string', 'max:100'],
            'code'          => ['required', 'string', 'alpha_dash', 'max:20', Rule::unique('teams', 'code')],
            'description'   => ['nullable', 'string', 'max:1000'],
            'is_active'     => ['nullable', 'boolean'],
            'leader_id'     => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $leaderId     = $this->input('leader_id');
            $departmentId = $this->input('department_id');

            if (is_int($leaderId) && is_int($departmentId)) {
                $leader = Employee::find($leaderId);

                if ($leader !== null && $leader->department_id !== $departmentId) {
                    $validator->errors()->add('leader_id', 'The team leader must belong to the selected department.');
                }
            }
        });
    }
}