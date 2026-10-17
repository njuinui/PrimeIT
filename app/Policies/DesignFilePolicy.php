<?php

namespace App\Policies;

use App\Models\DesignFile;
use App\Models\Project;
use App\Models\User;

class DesignFilePolicy
{
    public function view(User $user, DesignFile $file): bool
    {
        return $user->can('design.view') && $this->canSeeProject($user, $file->project_id);
    }

    /** Add a design file to a project. */
    public function create(User $user, Project $project): bool
    {
        return $user->can('design.manage') && $this->canWorkOn($user, $project);
    }

    /** Update, log versions, sync or remove. */
    public function manage(User $user, DesignFile $file): bool
    {
        if (! $user->can('design.manage')) {
            return false;
        }

        $project = Project::query()->find($file->project_id);

        return $project !== null && $this->canWorkOn($user, $project);
    }

    private function canSeeProject(User $user, int $projectId): bool
    {
        $project = Project::query()->find($projectId);

        return $project !== null && $user->can('view', $project);
    }

    private function canWorkOn(User $user, Project $project): bool
    {
        return $user->can('projects.view.all') || $project->isParticipant($user);
    }
}
