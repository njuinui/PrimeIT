<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\BudgetSpent;
use App\Domain\Finance\FinanceScope;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceScope $scope,
        private readonly BudgetSpent $spent,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('finance.view');

        $user   = $request->user();
        $months = (int) $request->query('months', 6);

        if ($months < 3 || $months > 24) {
            $months = 6;
        }

        $companyWide = $this->scope->companyWide($user);

        // One scoped fetch; everything below is collection math (portable SQL).
        $expenses = $this->scope->visibleExpenses($user)
            ->with(['category:id,uuid,name', 'department:id,uuid,name'])
            ->get(['id', 'user_id', 'expense_category_id', 'department_id', 'status',
                'amount', 'currency', 'expense_date']);

        $stats = [
            'draft_count'     => $expenses->where('status', 'draft')->count(),
            'pending_count'   => $expenses->where('status', 'submitted')->count(),
            'pending_amount'  => (float) $expenses->where('status', 'submitted')->sum('amount'),
            'approved_count'  => $expenses->where('status', 'approved')->count(),
            'approved_amount' => (float) $expenses->where('status', 'approved')->sum('amount'),
            'paid_count'      => $expenses->where('status', 'paid')->count(),
            'paid_amount'     => (float) $expenses->where('status', 'paid')->sum('amount'),
            'rejected_count'  => $expenses->where('status', 'rejected')->count(),
        ];

        $settled = $expenses->whereIn('status', ['approved', 'paid']);

        $byCategory = $settled
            ->groupBy(fn ($expense) => $expense->category?->name ?? 'Uncategorized')
            ->map(fn ($group, $category) => [
                'category' => $category,
                'amount'   => (float) $group->sum('amount'),
                'count'    => $group->count(),
            ])
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();

        $byDepartment = $settled
            ->filter(fn ($expense) => $expense->department !== null)
            ->groupBy(fn ($expense) => $expense->department->name)
            ->map(fn ($group, $department) => [
                'department' => $department,
                'amount'     => (float) $group->sum('amount'),
                'count'      => $group->count(),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        $byMonth = collect(range($months - 1, 0, -1))
            ->map(function (int $offset) use ($expenses) {
                $month = now()->subMonths($offset)->format('Y-m');
                $key   = $month.'-01';

                $inMonth = $expenses->filter(
                    fn ($expense) => $expense->expense_date?->format('Y-m') === $month,
                );

                return [
                    'month'     => $month,
                    'submitted' => (float) $inMonth->whereNotIn('status', ['draft'])->sum('amount'),
                    'paid'      => (float) $inMonth->where('status', 'paid')->sum('amount'),
                ];
            })
            ->values()
            ->all();

        $budgets = null;

        if ($this->scope->canSeeBudgets($user)) {
            $budgets = Budget::query()
                ->with(['project:id,uuid,name', 'department:id,uuid,name'])
                ->orderByDesc('amount')
                ->get()
                ->each(fn (Budget $budget) => $budget->spent = ($this->spent)($budget))
                ->map(fn (Budget $budget) => [
                    'id'          => $budget->uuid,
                    'subject'     => $budget->project?->name ?? $budget->department->name ?? '—',
                    'subject_type' => $budget->project_id !== null ? 'project' : 'department',
                    'description' => $budget->description,
                    'amount'      => (float) $budget->amount,
                    'spent'       => (float) $budget->spent,
                    'remaining'   => max(0.0, (float) $budget->amount - (float) $budget->spent),
                    'currency'    => $budget->currency,
                ])
                ->all();
        }

        return $this->success([
            'scope'    => $companyWide ? 'company' : 'own',
            'currency' => 'XAF',
            'months'   => $months,
            'stats'    => $stats,
            'by_category'   => $byCategory,
            'by_department' => $byDepartment,
            'by_month'      => $byMonth,
            'budgets'       => $budgets,
        ]);
    }
}