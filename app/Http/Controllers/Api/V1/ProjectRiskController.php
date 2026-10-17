<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreRiskRequest;
use App\Http\Requests\Projects\UpdateRiskRequest;
use App\Http\Resources\V1\RiskResource;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectRisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectRiskController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $risks = $project->risks()->with('owner')->orderByDesc('id')->get();

        return $this->success(RiskResource::collection($risks)->resolve());
    }

    public function store(StoreRiskRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $risk = $project->risks()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        ProjectActivity::record($project, $request->user(), 'risk_created', 'logged risk "'.$risk->title.'"', $risk);

        return $this->success(new RiskResource($risk->loadMissing('owner')), 'Risk logged.', 201);
    }

    public function update(UpdateRiskRequest $request, Project $project, ProjectRisk $risk): JsonResponse
    {
        $this->authorize('update', $project);
        $this->assertBelongsTo($project, $risk);

        $risk->update($request->validated());

        ProjectActivity::record($project, $request->user(), 'risk_updated', 'updated risk "'.$risk->title.'"', $risk);

        return $this->success(new RiskResource($risk->fresh()->loadMissing('owner')), 'Risk updated.');
    }

    public function destroy(Request $request, Project $project, ProjectRisk $risk): JsonResponse
    {
        $this->authorize('update', $project);
        $this->assertBelongsTo($project, $risk);

        ProjectActivity::record($project, $request->user(), 'risk_deleted', 'removed risk "'.$risk->title.'"');

        $risk->delete();

        return $this->success(message: 'Risk removed.');
    }

    private function assertBelongsTo(Project $project, ProjectRisk $risk): void
    {
        abort_unless($risk->project_id === $project->id, 404);
    }
}