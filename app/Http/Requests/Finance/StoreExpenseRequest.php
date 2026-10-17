<?php

namespace App\Http\Requests\Finance;

use App\Models\ExpenseCategory;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'expense_category_id' => $this->resolveId(ExpenseCategory::class, 'expense_category_id'),
            'project_id'          => $this->resolveId(Project::class, 'project_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title'               => ['required', 'string', 'max:200'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'project_id'          => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'amount'              => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'expense_date'        => ['required', 'date'],
            'receipt'             => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    private function resolveId(string $model, string $key): mixed
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $model::query()->where('uuid', $value)->value('id') ?? 'invalid';
    }
}