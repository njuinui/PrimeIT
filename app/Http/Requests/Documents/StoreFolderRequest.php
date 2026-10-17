<?php

namespace App\Http\Requests\Documents;

use App\Models\Department;
use App\Models\DocumentFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->resolveId(
                DocumentFolder::class,
                'parent_id'
            ),

            'department_id' => $this->resolveId(
                Department::class,
                'department_id'
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('document_folders', 'id'),
            ],

            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
        ];
    }

    private function resolveId(string $model, string $key): mixed
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $model::query()
            ->where('uuid', $value)
            ->value('id') ?? 'invalid';
    }
}