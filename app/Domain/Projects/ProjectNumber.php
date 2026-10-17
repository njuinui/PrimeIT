<?php

namespace App\Domain\Projects;

use App\Models\Project;

final class ProjectNumber
{
    public function next(): string
    {
        $latest = Project::withTrashed()->max('project_number');

        $next = $latest !== null
            ? ((int) substr((string) $latest, 4)) + 1
            : 1;

        return sprintf('PRJ-%04d', $next);
    }
}