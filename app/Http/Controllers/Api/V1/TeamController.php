<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreTeamRequest;
use App\Http\Requests\Company\UpdateTeamRequest;
use App\Http\Resources\V1\TeamResource;
use App\Models\Department;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('teams.view');

        $departmentId = $request->filled('department')
            ? Department::query()->where('uuid', $request->query('department'))->value('id')
            : null;

        $teams = Team::query()
            ->with(['department', 'leader'])
            ->withCount('employees')
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get();

        return $this->success(TeamResource::collection($teams)->resolve());
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $this->authorize('teams.manage');

        $team = Team::create([
            'department_id' => $request->validated('department_id'),
            'name'          => $request->validated('name'),
            'code'          => strtoupper($request->validated('code')),
            'description'   => $request->validated('description'),
            'leader_id'     => $request->validated('leader_id'),
            'is_active'     => (bool) $request->validated('is_active', true),
        ]);

        $team->loadMissing(['department', 'leader'])->loadCount('employees');

        return $this->success(new TeamResource($team), 'Team created.', 201);
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $this->authorize('teams.manage');

        $team->update([
            'department_id' => $request->validated('department_id'),
            'name'          => $request->validated('name'),
            'code'          => strtoupper($request->validated('code')),
            'description'   => $request->validated('description'),
            'leader_id'     => $request->validated('leader_id'),
            'is_active'     => (bool) $request->validated('is_active', $team->is_active),
        ]);

        $team = $team->fresh();
        $team->loadMissing(['department', 'leader'])->loadCount('employees');

        return $this->success(new TeamResource($team), 'Team updated.');
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->authorize('teams.manage');

        if ($team->employees()->exists()) {
            return $this->error('This team still has members. Reassign them to another team first.', 409);
        }

        $team->delete();

        return $this->success(message: 'Team deleted.');
    }
}