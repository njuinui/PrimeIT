<?php

namespace App\Domain\Projects;

use App\Models\Project;

/**
 * Progress derivation:
 *  - milestones present → completed milestone ratio;
 *  - otherwise          → completed task ratio (cancelled tasks excluded).
 * Phase 7 hooks task status changes into this same action.
 */
final class RecalculateProjectProgress
{
    public function __invoke(Project $project): Project
    {
        $milestoneCount = $project->milestones()->count();

        if ($milestoneCount > 0) {
            $completed = $project->milestones()->whereNotNull('completed_at')->count();

            $progress = (int) round($completed / $milestoneCount * 100);
        } else {
            $total     = $project->tasks()->whereNotIn('status', ['cancelled'])->count();
            $completed = $project->tasks()->where('status', 'completed')->count();

            $progress = $total > 0 ? (int) round($completed / $total * 100) : $project->progress;
        }

        // A completed project is always 100%.
        if ($project->status === Project::STATUS_COMPLETED) {
            $progress = 100;
        }

        $project->forceFill(['progress' => $progress])->save();

        return $project->fresh();
    }
}