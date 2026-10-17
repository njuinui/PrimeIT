<?php

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'                    => ['required', 'string', 'max:20000'],
            'action_items'               => ['present', 'array', 'max:20'],
            'action_items.*.title'       => ['required', 'string', 'max:200'],
            'action_items.*.assignee_id' => ['nullable', 'string', Rule::exists('employees', 'uuid')],
            'action_items.*.due_date'    => ['nullable', 'date'],
            'action_items.*.task_id'     => ['nullable', 'string'],
        ];
    }
}