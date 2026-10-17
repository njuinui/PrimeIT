<?php

namespace App\Http\Requests\Finance;

use App\Models\Department;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id'    => $this->resolveId(Project::class, 'project_id'),
            'department_id' => $this->resolveId(Department::class, 'department_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'project_id'    => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'description'   => ['nullable', 'string', 'max:255'],
            'amount'        => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasProject    = is_int($this->input('project_id'));
            $hasDepartment = is_int($this->input('department_id'));

            if ($hasProject === $hasDepartment) {
                $validator->errors()->add(
                    'project_id',
                    'A budget must target exactly one project or one department.',
                );
            }

            if ($this->filled('start_date') && $this->filled('end_date')
                && strtotime((string) $this->input('end_date')) < strtotime((string) $this->input('start_date'))) {
                $validator->errors()->add('end_date', 'The end date must be on or after the start date.');
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