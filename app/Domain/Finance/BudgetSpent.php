<?php

namespace App\Domain\Finance;

use App\Models\Budget;
use App\Models\Expense;

/**
 * Spent = approved + paid expenses attributed to the budget's subject
 * (project or department), optionally within the budget period.
 */
final class BudgetSpent
{
    public function __invoke(Budget $budget): float
    {
        $query = Expense::query()
            ->whereIn('status', ['approved', 'paid'])
            ->where('currency', $budget->currency);

        if ($budget->project_id !== null) {
            $query->where('project_id', $budget->project_id);
        } else {
            $query->where('department_id', $budget->department_id);
        }

        if ($budget->start_date !== null) {
            $query->whereDate('expense_date', '>=', $budget->start_date);
        }

        if ($budget->end_date !== null) {
            $query->whereDate('expense_date', '<=', $budget->end_date);
        }

        return (float) $query->sum('amount');
    }
}