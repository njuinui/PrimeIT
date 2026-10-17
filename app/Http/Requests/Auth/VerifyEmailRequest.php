<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id'        => ['required', 'integer'],
            'hash'      => ['required', 'string', 'size:40'],
            'expires'   => ['required', 'integer'],
            'signature' => ['required', 'string'],
        ];
    }
}