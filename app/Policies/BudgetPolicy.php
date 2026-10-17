<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view.all') || $user->can('budgets.manage');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('budgets.manage');
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->can('budgets.manage');
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->can('budgets.manage');
    }
}