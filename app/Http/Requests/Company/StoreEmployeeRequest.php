<?php

namespace App\Http\Requests\Company;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // enforced in the controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'department_id' => $this->resolveId(Department::class, 'department_id'),
            'team_id'       => $this->resolveId(Team::class, 'team_id'),
            'manager_id'    => $this->resolveId(Employee::class, 'manager_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:100'],
            'middle_name'     => ['nullable', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone'           => ['nullable', 'string', 'max:30'],
            'personal_email'  => ['nullable', 'string', 'email', 'max:255'],
            'job_title'       => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', 'string', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'hire_date'       => ['nullable', 'date'],
            'status'          => ['required', 'string', Rule::in(['active', 'inactive'])],
            'department_id'   => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'team_id'         => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'manager_id'      => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'avatar'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $teamId       = $this->input('team_id');
            $departmentId = $this->input('department_id');

            if (is_int($teamId) && is_int($departmentId)) {
                $team = Team::find($teamId);

                if ($team !== null && $team->department_id !== $departmentId) {
                    $validator->errors()->add('team_id', 'The selected team does not belong to the selected department.');
                }
            }
        });
    }

    private function resolveId(string $model, string $key): mixed
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $model::query()->where('uuid', $value)->value('id') ?? 'invalid';
    }
}