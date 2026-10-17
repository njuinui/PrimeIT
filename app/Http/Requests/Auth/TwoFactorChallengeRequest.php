<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'uuid'],
            'code'            => ['required_without:recovery_code', 'nullable', 'digits:6'],
            'recovery_code'   => ['required_without:code', 'nullable', 'string', 'max:20'],
            'device_name'     => ['nullable', 'string', 'max:100'],
        ];
    }
}