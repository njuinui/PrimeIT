<?php

namespace App\Http\Requests\Projects;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_id'            => $this->resolveEmployeeId('owner_id'),
            'project_manager_id'  => $this->resolveEmployeeId('project_manager_id'),
            'department_id'       => $this->resolveId(Department::class, 'department_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:5000'],
            'status'              => ['required', 'string', Rule::in(Project::STATUSES)],
            'priority'            => ['required', 'string', Rule::in(Project::PRIORITIES)],
            'owner_id'            => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'project_manager_id'  => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'department_id'       => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'start_date'          => ['nullable', 'date'],
            'due_date'            => ['nullable', 'date'],
            'budget'              => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency'            => ['nullable', 'string', 'size:3'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('start_date') && $this->filled('due_date')) {
                if (strtotime((string) $this->input('due_date')) < strtotime((string) $this->input('start_date'))) {
                    $validator->errors()->add('due_date', 'The due date must be on or after the start date.');
                }
            }
        });
    }

    protected function resolveEmployeeId(string $key): mixed
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return Employee::query()->where('uuid', $value)->value('id') ?? 'invalid';
    }

    protected function resolveId(string $model, string $key): mixed
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $model::query()->where('uuid', $value)->value('id') ?? 'invalid';
    }
}