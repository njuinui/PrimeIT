<?php

namespace App\Http\Requests\Meetings;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $organizer = $this->input('organized_by');

        $this->merge([
            'organized_by' => $organizer === null || $organizer === ''
                ? null
                : Employee::query()->where('uuid', $organizer)->value('id') ?? 'invalid',
        ]);
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:10000'],
            'location'     => ['nullable', 'string', 'max:255'],
            'video_link'   => ['nullable', 'url', 'max:500'],
            'starts_at'    => ['required', 'date'],
            'ends_at'      => ['required', 'date'],
            'organized_by' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'participants' => ['present', 'array', 'max:50'],
            'participants.*.employee_id' => ['required', 'string', Rule::exists('employees', 'uuid')],
            'participants.*.attendance'  => ['required', 'string', Rule::in(['required', 'optional'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('starts_at') && $this->filled('ends_at')) {
                if (strtotime((string) $this->input('ends_at')) <= strtotime((string) $this->input('starts_at'))) {
                    $validator->errors()->add('ends_at', 'The end time must be after the start time.');
                }
            }
        });
    }
}