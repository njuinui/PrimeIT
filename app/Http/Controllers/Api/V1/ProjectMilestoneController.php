<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Projects\RecalculateProjectProgress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreMilestoneRequest;
use App\Http\Requests\Projects\UpdateMilestoneRequest;
use App\Http\Resources\V1\MilestoneResource;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    public function __construct(private readonly RecalculateProjectProgress $recalculateProgress) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $milestones = $project->milestones()->get();

        return $this->success(MilestoneResource::collection($milestones)->resolve());
    }

    public function store(StoreMilestoneRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $milestone = $project->milestones()->create([
            ...collect($request->validated())->except('completed')->all(),
            'sort_order'  => $request->validated('sort_order') ?? ((int) $project->milestones()->max('sort_order')) + 1,
            'created_by'  => $request->user()->id,
        ]);

        ProjectActivity::record($project, $request->user(), 'milestone_created', 'created milestone "'.$milestone->title.'"', $milestone);

        $project = ($this->recalculateProgress)($project);

        return $this->success(new MilestoneResource($milestone), 'Milestone created.', 201);
    }

    public function update(UpdateMilestoneRequest $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);
        $this->assertBelongsTo($project, $milestone);

        $data = collect($request->validated())->except('completed');

        $wasCompleted = $milestone->completed_at !== null;
        $setCompleted = $request->boolean('completed', $wasCompleted);

        if ($setCompleted && ! $wasCompleted) {
            $data['completed_at'] = now();
        } elseif (! $setCompleted && $wasCompleted) {
            $data['completed_at'] = null;
        }

        $milestone->update($data->all());

        if ($setCompleted && ! $wasCompleted) {
            ProjectActivity::record($project, $request->user(), 'milestone_completed', 'completed milestone "'.$milestone->title.'"', $milestone);
        } elseif (! $setCompleted && $wasCompleted) {
            ProjectActivity::record($project, $request->user(), 'milestone_reopened', 'reopened milestone "'.$milestone->title.'"', $milestone);
        } else {
            ProjectActivity::record($project, $request->user(), 'milestone_updated', 'updated milestone "'.$milestone->title.'"', $milestone);
        }

        ($this->recalculateProgress)($project);

        return $this->success(new MilestoneResource($milestone->fresh()), 'Milestone updated.');
    }

    /**
     * One-click completion toggle for the UI checklist.
     */
    public function toggle(Request $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);
        $this->assertBelongsTo($project, $milestone);

        $completing = $milestone->completed_at === null;

        $milestone->forceFill(['completed_at' => $completing ? now() : null])->save();

        ProjectActivity::record(
            $project,
            $request->user(),
            $completing ? 'milestone_completed' : 'milestone_reopened',
            ($completing ? 'completed' : 'reopened').' milestone "'.$milestone->title.'"',
            $milestone,
        );

        $project = ($this->recalculateProgress)($project);

        return $this->success([
            'milestone' => new MilestoneResource($milestone->fresh()),
            'progress'  => (int) $project->progress,
        ]);
    }

    public function destroy(Request $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);
        $this->assertBelongsTo($project, $milestone);

        ProjectActivity::record($project, $request->user(), 'milestone_deleted', 'deleted milestone "'.$milestone->title.'"');

        $milestone->delete();

        ($this->recalculateProgress)($project);

        return $this->success(message: 'Milestone deleted.');
    }

    private function assertBelongsTo(Project $project, ProjectMilestone $milestone): void
    {
        abort_unless($milestone->project_id === $project->id, 404);
    }
}