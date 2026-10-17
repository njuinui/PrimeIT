<?php

namespace App\Http\Requests\Projects;

use App\Models\Employee;
use App\Models\ProjectRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $owner = $this->input('owner_id');

        $this->merge([
            'owner_id' => $owner === null || $owner === ''
                ? null
                : Employee::query()->where('uuid', $owner)->value('id') ?? 'invalid',
        ]);
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'impact'      => ['required', 'string', Rule::in(ProjectRisk::IMPACTS)],
            'likelihood'  => ['required', 'string', Rule::in(ProjectRisk::LIKELIHOODS)],
            'status'      => ['required', 'string', Rule::in(ProjectRisk::STATUSES)],
            'mitigation'  => ['nullable', 'string', 'max:2000'],
            'owner_id'    => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'review_date' => ['nullable', 'date'],
        ];
    }
}