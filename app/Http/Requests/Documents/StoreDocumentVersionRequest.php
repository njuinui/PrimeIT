<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file',
                'mimes:jpg,jpeg,png,gif,pdf,txt,md,csv,doc,docx,xls,xlsx,ppt,pptx,zip',
                'max:20480'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}