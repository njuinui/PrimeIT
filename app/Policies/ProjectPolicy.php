<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        if (! $user->can('projects.view')) {
            return false;
        }

        return $user->can('projects.view.all') || $project->isParticipant($user);
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    /**
     * Full write: projects.update plus either company-wide oversight
     * (projects.view.all) or a direct stake (owner / PM / manager-member).
     */
    public function update(User $user, Project $project): bool
    {
        if (! $user->can('projects.update')) {
            return false;
        }

        if ($user->can('projects.view.all')) {
            return true;
        }

        $employee = $user->employee()->first();

        if ($employee === null) {
            return false;
        }

        if ($project->owner_id === $employee->id || $project->project_manager_id === $employee->id) {
            return true;
        }

        return $project->members()
            ->where('project_members.employee_id', $employee->id)
            ->where('project_members.role', 'manager')
            ->exists();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.delete');
    }

    public function manageMembers(User $user, Project $project): bool
    {
        if ($this->update($user, $project)) {
            return true;
        }

        return $user->can('projects.members.manage') && $project->isParticipant($user);
    }
}