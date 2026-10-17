<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCategoryRequest;
use App\Http\Resources\V1\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('finance.view');

        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        return $this->success(ExpenseCategoryResource::collection($categories)->resolve());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('budgets.manage');

        $category = ExpenseCategory::create([
            'name'        => $request->validated('name'),
            'description' => $request->validated('description'),
            'is_active'   => (bool) $request->validated('is_active', true),
            'created_by'  => $request->user()->id,
        ]);

        return $this->success(new ExpenseCategoryResource($category), 'Category created.', 201);
    }

    public function update(StoreCategoryRequest $request, ExpenseCategory $category): JsonResponse
    {
        $this->authorize('budgets.manage');

        $category->update([
            'name'        => $request->validated('name'),
            'description' => $request->validated('description'),
            'is_active'   => (bool) $request->validated('is_active', $category->is_active),
        ]);

        return $this->success(new ExpenseCategoryResource($category->fresh()), 'Category updated.');
    }

    public function destroy(Request $request, ExpenseCategory $category): JsonResponse
    {
        $this->authorize('budgets.manage');

        if ($category->expenses()->exists()) {
            return $this->error('This category still has expenses recorded against it.', 409);
        }

        $category->delete();

        return $this->success(message: 'Category deleted.');
    }
}