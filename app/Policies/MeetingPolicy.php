<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('meetings.view');
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if (! $user->can('meetings.view')) {
            return false;
        }

        if ($user->can('meetings.manage')) {
            return true;
        }

        return $this->participantEmployeeId($user, $meeting) !== null
            || $this->isOrganizer($user, $meeting);
    }

    public function create(User $user): bool
    {
        return $user->can('meetings.manage');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->can('meetings.manage');
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->can('meetings.manage');
    }

    public function respond(User $user, Meeting $meeting): bool
    {
        return $this->participantEmployeeId($user, $meeting) !== null;
    }

    public function manageMinutes(User $user, Meeting $meeting): bool
    {
        return $user->can('meetings.manage');
    }

    private function participantEmployeeId(User $user, Meeting $meeting): ?int
    {
        $employee = $user->employee()->first();

        if ($employee === null) {
            return null;
        }

        return $meeting->participants()->where('employees.id', $employee->id)->exists()
            ? $employee->id
            : null;
    }

    private function isOrganizer(User $user, Meeting $meeting): bool
    {
        $employee = $user->employee()->first();

        return $employee !== null && $meeting->organized_by === $employee->id;
    }
}