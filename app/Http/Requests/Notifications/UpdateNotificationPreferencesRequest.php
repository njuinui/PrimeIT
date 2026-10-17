<?php

namespace App\Http\Requests\Notifications;

use App\Support\Notifications\Categories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences'            => ['present', 'array'],
            'preferences.*.category' => ['required', 'string', Rule::in(array_keys(Categories::all()))],
            'preferences.*.in_app'   => ['required', 'boolean'],
            'preferences.*.email'    => ['required', 'boolean'],
        ];
    }
}