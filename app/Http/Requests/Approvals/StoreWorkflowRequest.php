<?php

namespace App\Http\Requests\Approvals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:150', Rule::unique('approval_workflows', 'name')->ignore($this->route('workflow'))],
            'entity_type' => ['required', 'string', Rule::in(['expense'])],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['nullable', 'boolean'],
            'steps'       => ['present', 'array', 'min:1', 'max:10'],
            'steps.*.name'           => ['required', 'string', 'max:100'],
            'steps.*.approver_source' => ['required', 'string', Rule::in(['role', 'requester_manager', 'specific_users'])],
            'steps.*.approver_role'   => ['nullable', 'required_if:steps.*.approver_source,role', 'string', Rule::exists('roles', 'name')],
            'steps.*.approver_users'  => ['nullable', 'array', 'required_if:steps.*.approver_source,specific_users'],
            'steps.*.approver_users.*' => ['string', Rule::exists('users', 'uuid')],
            'steps.*.policy'          => ['required', 'string', Rule::in(['any_one', 'all'])],
            'steps.*.escalation_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}