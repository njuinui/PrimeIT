<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('employees.view');
    }

    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    /**
     * Full access via employees.update, OR line-management access:
     * any manager up the chain may edit the people reporting to them.
     */
    public function update(User $user, Employee $employee): bool
    {
        if ($user->can('employees.update')) {
            return true;
        }

        return $employee->user_id !== $user->id
            && $employee->isManagedBy($user);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete')
            && $employee->user_id !== $user->id;
    }
}