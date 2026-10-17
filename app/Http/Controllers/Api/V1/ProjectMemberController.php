<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectMembersRequest;
use App\Http\Requests\Projects\UpdateProjectMemberRoleRequest;
use App\Http\Resources\V1\ProjectMemberResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProjectMemberController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $members = $project->members()->with('department')->orderBy('first_name')->get();

        return $this->success(ProjectMemberResource::collection($members)->resolve());
    }

    public function store(StoreProjectMembersRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $role = (string) $request->validated('role');

        $requested = Employee::query()
            ->whereIn('uuid', $request->validated('employees'))
            ->get();

        $existingIds = $project->members()->pluck('employees.id');

        $new = $requested->where('id', '!==', null)
            ->reject(fn (Employee $employee) => $existingIds->contains($employee->id));

        if ($new->isNotEmpty()) {
            $project->members()->attach(
                $new->mapWithKeys(fn (Employee $employee) => [
                    $employee->id => ['role' => $role, 'added_by' => $request->user()->id],
                ])->all(),
            );

            $names = $new->take(3)->map(fn (Employee $employee) => trim($employee->first_name.' '.$employee->last_name))->implode(', ');
            $suffix = $new->count() > 3 ? sprintf(' and %d more', $new->count() - 3) : '';

            ProjectActivity::record(
                $project,
                $request->user(),
                'member_added',
                'added '.$names.$suffix.' to the project',
            );
        }

        $members = $project->members()->with('department')->orderBy('first_name')->get();

        return $this->success(
            ProjectMemberResource::collection($members)->resolve(),
            $new->isEmpty() ? 'No new members to add.' : sprintf('%d member(s) added.', $new->count()),
        );
    }

    public function update(UpdateProjectMemberRoleRequest $request, Project $project, Employee $employee): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        if (! $this->isMember($project, $employee)) {
            return $this->error('This employee is not a member of the project.', 404);
        }

        $project->members()->updateExistingPivot($employee->id, [
            'role' => (string) $request->validated('role'),
        ]);

        ProjectActivity::record(
            $project,
            $request->user(),
            'member_role_changed',
            sprintf('changed %s\'s project role to %s', trim($employee->first_name.' '.$employee->last_name), $request->validated('role')),
            $employee,
        );

        $members = $project->members()->with('department')->orderBy('first_name')->get();

        return $this->success(ProjectMemberResource::collection($members)->resolve(), 'Member role updated.');
    }

    public function destroy(Request $request, Project $project, Employee $employee): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        if (! $this->isMember($project, $employee)) {
            return $this->error('This employee is not a member of the project.', 404);
        }

        $project->members()->detach($employee->id);

        ProjectActivity::record(
            $project,
            $request->user(),
            'member_removed',
            'removed '.trim($employee->first_name.' '.$employee->last_name).' from the project',
            $employee,
        );

        $members = $project->members()->with('department')->orderBy('first_name')->get();

        return $this->success(ProjectMemberResource::collection($members)->resolve(), 'Member removed.');
    }

    private function isMember(Project $project, Employee $employee): bool
    {
        return $project->members()->where('employees.id', $employee->id)->exists();
    }
}