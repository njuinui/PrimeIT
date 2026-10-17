<?php

namespace App\Policies;

use App\Domain\Tasks\TaskAccess;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function __construct(private readonly TaskAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->access->canView($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->access->canUpdate($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->access->canDelete($user, $task);
    }

    public function manageAssignees(User $user, Task $task): bool
    {
        return $this->access->canAssign($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $user->can('tasks.comment') && $this->access->canView($user, $task);
    }
}