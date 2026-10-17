<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Work\ContributionStats;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContributionController extends Controller
{
    public function __construct(private readonly ContributionStats $stats) {}

    public function project(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($request->user()->can('work.view'), 403);

        return $this->success($this->stats->forProject($project, $this->days($request, 90)));
    }

    public function team(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('work.view') && $user->can('teams.view'), 403);

        $myTeamId = Employee::query()->where('user_id', $user->id)->value('team_id');

        abort_unless(
            $user->can('reports.view.all') || $myTeamId === $team->id || $this->leadsTeam($user, $team),
            403,
            'You can only view the progress of your own team.',
        );

        return $this->success($this->stats->forTeam($team, $this->days($request, 90)));
    }

    /** `me` or a user uuid. */
    public function user(Request $request, string $user): JsonResponse
    {
        $viewer = $request->user();

        abort_unless($viewer->can('work.view'), 403);

        $target = $user === 'me' ? $viewer : User::query()->where('uuid', $user)->firstOrFail();

        if ($target->id !== $viewer->id) {
            $theirs = Employee::query()->where('user_id', $target->id)->first();
            $mine   = Employee::query()->where('user_id', $viewer->id)->first();

            $allowed = $viewer->can('reports.view.all')
                || ($theirs !== null && $mine !== null && $theirs->team_id !== null && $theirs->team_id === $mine->team_id)
                || ($theirs !== null && $theirs->isManagedBy($viewer));

            abort_unless($allowed, 403, 'You can only view people on your team or who report to you.');
        }

        return $this->success($this->stats->forUser($target, $this->days($request, 365)));
    }

    private function days(Request $request, int $default): int
    {
        return max(7, min((int) $request->query('days', $default), 365));
    }

    private function leadsTeam(User $user, Team $team): bool
    {
        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');

        return $employeeId !== null && $team->leader_id === $employeeId;
    }
}
