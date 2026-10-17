<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by TaskPolicy in the controller
    }

    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'title'             => ['sometimes', 'string', 'max:255'],
            'description'       => ['sometimes', 'nullable', 'string'],
            'status'            => ['sometimes', 'string', Rule::in(Task::STATUSES)],
            'priority'          => ['sometimes', 'string', Rule::in(Task::PRIORITIES)],
            'parent_task_id'    => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
            'start_date'        => ['sometimes', 'nullable', 'date'],
            'due_date'          => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'progress'          => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sort_order'        => ['sometimes', 'integer', 'min:0'],
            'is_recurring'      => ['sometimes', 'boolean'],
        ];
    }
}