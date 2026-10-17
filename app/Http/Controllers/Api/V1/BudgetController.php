<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\BudgetSpent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBudgetRequest;
use App\Http\Requests\Finance\UpdateBudgetRequest;
use App\Http\Resources\V1\BudgetResource;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetSpent $spent) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        $budgets = Budget::query()
            ->with(['project', 'department'])
            ->orderByDesc('amount')
            ->get()
            ->each(fn (Budget $budget) => $budget->spent = ($this->spent)($budget));

        return $this->success(BudgetResource::collection($budgets)->resolve());
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $this->authorize('create', Budget::class);

        $budget = Budget::create([
            ...collect($request->validated())->except('currency')->all(),
            'currency'   => 'XAF',
            'created_by' => $request->user()->id,
        ]);

        $budget->spent = ($this->spent)($budget);
        $budget->loadMissing(['project', 'department']);

        return $this->success(new BudgetResource($budget), 'Budget created.', 201);
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        $this->authorize('update', Budget::class);

        $budget->update(collect($request->validated())->except('currency')->all());

        $budget = $budget->fresh();
        $budget->spent = ($this->spent)($budget);
        $budget->loadMissing(['project', 'department']);

        return $this->success(new BudgetResource($budget), 'Budget updated.');
    }

    public function destroy(Request $request, Budget $budget): JsonResponse
    {
        $this->authorize('delete', Budget::class);

        $budget->delete();

        return $this->success(message: 'Budget deleted.');
    }
}