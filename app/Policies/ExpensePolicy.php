<?php

namespace App\Policies;

use App\Domain\Finance\FinanceScope;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function __construct(private readonly FinanceScope $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('finance.view')
            && ($this->scope->companyWide($user) || $expense->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $expense->user_id === $user->id && $expense->status === 'draft';
    }

    public function submit(User $user, Expense $expense): bool
    {
        return $expense->user_id === $user->id && in_array($expense->status, ['draft', 'rejected'], true);
    }

    public function decide(User $user, Expense $expense): bool
    {
        return $user->can('expenses.approve') && $this->view($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        if ($expense->user_id === $user->id && $expense->status === 'draft') {
            return true;
        }

        return $user->can('finance.view.all') && $expense->status !== 'paid';
    }
}