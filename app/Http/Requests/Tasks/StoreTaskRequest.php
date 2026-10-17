<?php

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id'     => $this->resolveId(Project::class, 'project_id'),
            'parent_task_id' => $this->resolveId(Task::class, 'parent_task_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:200'],
            'description'       => ['nullable', 'string', 'max:10000'],
            'project_id'        => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'parent_task_id'    => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'status'            => ['required', 'string', Rule::in(Task::STATUSES)],
            'priority'          => ['required', 'string', Rule::in(Task::PRIORITIES)],
            'assignees'         => ['nullable', 'array', 'max:10'],
            'assignees.*'       => ['string', Rule::exists('employees', 'uuid')],
            'start_date'        => ['nullable', 'date'],
            'due_date'          => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
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

    private function resolveId(string $model, string $key): mixed
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $model::query()->where('uuid', $value)->value('id') ?? 'invalid';
    }
}