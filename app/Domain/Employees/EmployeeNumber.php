<?php

namespace App\Domain\Employees;

use App\Models\Employee;

final class EmployeeNumber
{
    /**
     * Next sequential EMP-#### number (portable across MySQL/SQLite;
     * zero-padded values sort lexically for the 4-digit range).
     */
    public function next(): string
    {
        $latest = Employee::withTrashed()->max('employee_number');

        $next = $latest !== null
            ? ((int) substr((string) $latest, 4)) + 1
            : 1;

        return sprintf('EMP-%04d', $next);
    }
}