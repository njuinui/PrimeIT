<?php

namespace App\Domain\Finance;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;

final class FinanceScope
{
    /**
     * Approvers must see the queue; company-wide readers see everything.
     */
    public function companyWide(User $user): bool
    {
        return $user->can('finance.view.all') || $user->can('expenses.approve');
    }

    public function canSeeBudgets(User $user): bool
    {
        return $user->can('finance.view.all') || $user->can('budgets.manage');
    }

    /**
     * Expense query pre-scoped to what this user may see.
     */
    public function visibleExpenses(User $user)
    {
        if ($this->companyWide($user)) {
            return Expense::query();
        }

        return Expense::query()->where('user_id', $user->id);
    }
}