<?php

namespace App\Http\Requests\Documents;

use App\Models\DocumentFolder;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'folder_id' => $this->resolveId(
                DocumentFolder::class,
                'folder_id'
            ),

            'project_id' => $this->resolveId(
                Project::class,
                'project_id'
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:200',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'tags' => [
                'nullable',
                'array',
                'max:10',
            ],

            'tags.*' => [
                'string',
                'max:30',
            ],

            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('document_folders', 'id'),
            ],

            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id'),
            ],

            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,gif,pdf,txt,md,csv,doc,docx,xls,xlsx,ppt,pptx,zip',
                'max:20480',
            ],
        ];
    }

    private function resolveId(string $model, string $key): mixed
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        $id = $model::query()
            ->where('uuid', $value)
            ->value('id');

        return $id ?? null;
    }
}