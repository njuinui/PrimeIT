<?php

namespace App\Domain\Tasks;

use App\Models\Employee;
use App\Models\User;

final class TaskScope
{
    private ?Employee $employeeCache = null;
    private bool $employeeResolved = false;

    /** @var array<int, int>|null */
    private ?array $subtreeCache = null;

    public function __construct(private readonly User $user) {}

    /**
     * The dashboard's primary framing for this user.
     */
    public function primary(): string
    {
        if ($this->user->can('tasks.view.all') || $this->employee() === null) {
            return 'company';
        }

        return $this->hasDirectReports() ? 'team' : 'personal';
    }

    /**
     * Assignee employee ids whose tasks the user may see on dashboards,
     * or null when unrestricted (tasks.view.all).
     *
     * @return array<int, int>|null
     */
    public function employeeIds(): ?array
    {
        if ($this->user->can('tasks.view.all')) {
            return null;
        }

        $employee = $this->employee();

        return $employee === null ? null : $this->subtreeIds($employee);
    }

    public function employee(): ?Employee
    {
        if (! $this->employeeResolved) {
            $this->employeeCache = $this->user->employee()->first();
            $this->employeeResolved = true;
        }

        return $this->employeeCache;
    }

    public function hasDirectReports(): bool
    {
        $employee = $this->employee();

        return $employee !== null
            && Employee::query()->where('manager_id', $employee->id)->exists();
    }

    /**
     * The employee plus everyone beneath them in the management tree.
     *
     * @return array<int, int>
     */
    public function subtreeIds(Employee $employee): array
    {
        if ($this->subtreeCache !== null) {
            return $this->subtreeCache;
        }

        $ids = [$employee->id];
        $frontier = [$employee->id];

        while ($frontier !== []) {
            $next = Employee::query()
                ->whereIn('manager_id', $frontier)
                ->pluck('id')
                ->all();

            $next = array_values(array_diff($next, $ids));
            $ids = array_merge($ids, $next);
            $frontier = $next;
        }

        return $this->subtreeCache = $ids;
    }
}