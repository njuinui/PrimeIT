<?php

namespace App\Http\Requests\Company;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['department_id' => Department::class, 'team_id' => Team::class, 'manager_id' => Employee::class] as $key => $model) {
            $value = $this->input($key);
            $this->merge([$key => $value === null || $value === '' ? null : ($model::query()->where('uuid', $value)->value('id') ?? 'invalid')]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:100'],
            'middle_name'     => ['nullable', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'personal_email'  => ['nullable', 'string', 'email', 'max:255'],
            'job_title'       => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', 'string', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'hire_date'       => ['nullable', 'date'],
            'status'          => ['required', 'string', Rule::in(['active', 'inactive'])],
            'department_id'   => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'team_id'         => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'manager_id'      => ['nullable', 'integer', Rule::exists('employees', 'id'), Rule::notIn([$this->route('employee')?->id])],
            'avatar'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_avatar'   => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $teamId = $this->input('team_id');
            $departmentId = $this->input('department_id');

            if (is_int($teamId) && is_int($departmentId)) {
                $team = Team::find($teamId);

                if ($team !== null && $team->department_id !== $departmentId) {
                    $validator->errors()->add('team_id', 'The selected team does not belong to the selected department.');
                }
            }
        });
    }
}