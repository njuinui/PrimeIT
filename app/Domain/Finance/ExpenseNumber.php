<?php

namespace App\Domain\Finance;

use App\Models\Expense;

final class ExpenseNumber
{
    public function next(): string
    {
        $latest = Expense::withTrashed()->max('expense_number');

        $next = $latest !== null
            ? ((int) substr((string) $latest, 4)) + 1
            : 1;

        return sprintf('EXP-%04d', $next);
    }
}