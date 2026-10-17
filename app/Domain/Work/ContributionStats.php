<?php

namespace App\Domain\Work;

use App\Models\DesignFile;
use App\Models\DesignVersion;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkUpdate;
use App\Support\PersonPayload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GitHub-style insights: per-person contribution rows, a daily activity
 * series (the contribution graph) and project / team roll-ups.
 */
final class ContributionStats
{
    // ── Project ───────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function forProject(Project $project, int $days = 90): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $participantUserIds = Employee::query()
            ->whereIn('id', $this->projectEmployeeIds($project))
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $base = WorkUpdate::query()->where('project_id', $project->id);

        $contributors = $this->memberRows($participantUserIds, $project, $since);

        return [
            'range_days' => $days,
            'project'    => [
                'id'       => $project->uuid,
                'progress' => $project->progress,
                'status'   => $project->status,
            ],
            'totals' => [
                'updates'      => (clone $base)->where('committed_at', '>=', $since)->count(),
                'minutes'      => (int) (clone $base)->where('committed_at', '>=', $since)->sum('minutes_spent'),
                'contributors' => $contributors->where('updates', '>', 0)->count(),
                'last_activity_at' => (clone $base)->max('committed_at'),
            ],
            'tasks_by_status'  => Task::query()
                ->where('project_id', $project->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'designs_by_status' => DesignFile::query()
                ->where('project_id', $project->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'design_progress' => (int) round((float) DesignFile::query()->where('project_id', $project->id)->avg('progress')),
            'daily'        => $this->daily($base, $since, $days),
            'contributors' => $contributors->values()->all(),
        ];
    }

    // ── Team ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function forTeam(Team $team, int $days = 90): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $employees = Employee::query()->where('team_id', $team->id)->whereNotNull('user_id')->get(['id', 'user_id']);
        $userIds   = $employees->pluck('user_id');

        $base = WorkUpdate::query()->whereIn('user_id', $userIds);

        $projectIds = (clone $base)->where('committed_at', '>=', $since)->distinct()->pluck('project_id');

        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->get(['id', 'uuid', 'name', 'status', 'progress'])
            ->map(function (Project $project) use ($userIds, $since) {
                return [
                    'id'       => $project->uuid,
                    'name'     => $project->name,
                    'status'   => $project->status,
                    'progress' => $project->progress,
                    'updates'  => WorkUpdate::query()
                        ->where('project_id', $project->id)
                        ->whereIn('user_id', $userIds)
                        ->where('committed_at', '>=', $since)
                        ->count(),
                ];
            })
            ->sortByDesc('updates')
            ->values()
            ->all();

        $taskRows = DB::table('task_assignees')
            ->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')
            ->whereIn('task_assignees.employee_id', $employees->pluck('id'))
            ->whereNull('tasks.deleted_at')
            ->selectRaw('tasks.status as status, COUNT(*) as total')
            ->groupBy('tasks.status')
            ->pluck('total', 'status');

        $members = $this->memberRows($userIds, null, $since);

        return [
            'range_days' => $days,
            'team'       => ['id' => $team->uuid, 'name' => $team->name],
            'totals'     => [
                'updates'  => (clone $base)->where('committed_at', '>=', $since)->count(),
                'minutes'  => (int) (clone $base)->where('committed_at', '>=', $since)->sum('minutes_spent'),
                'members'  => $userIds->count(),
                'active_members' => $members->where('updates', '>', 0)->count(),
            ],
            'tasks_by_status' => $taskRows,
            'projects'        => $projects,
            'daily'           => $this->daily($base, $since, $days),
            'contributors'    => $members->values()->all(),
        ];
    }

    // ── Individual ────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, int $days = 365): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $base  = WorkUpdate::query()->where('user_id', $user->id);
        $daily = $this->daily($base, $since, $days);

        $employee = Employee::query()->where('user_id', $user->id)->first(['id']);

        $tasks = $employee === null ? null : DB::table('task_assignees')
            ->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')
            ->where('task_assignees.employee_id', $employee->id)
            ->whereNull('tasks.deleted_at')
            ->selectRaw('COUNT(*) as assigned, SUM(CASE WHEN task_assignees.completed_at IS NOT NULL THEN 1 ELSE 0 END) as done')
            ->first();

        $perProject = (clone $base)
            ->where('committed_at', '>=', $since)
            ->selectRaw('project_id, COUNT(*) as updates, COALESCE(SUM(minutes_spent), 0) as minutes')
            ->groupBy('project_id')
            ->orderByDesc('updates')
            ->limit(8)
            ->get();

        $projects = Project::query()
            ->whereIn('id', $perProject->pluck('project_id'))
            ->get(['id', 'uuid', 'name', 'status', 'progress'])
            ->keyBy('id');

        [$current, $longest] = $this->streaks($daily);

        return [
            'range_days' => $days,
            'user'       => PersonPayload::make($user->loadMissing('employee')),
            'totals'     => [
                'updates'         => array_sum(array_column($daily, 'count')),
                'minutes'         => (int) (clone $base)->where('committed_at', '>=', $since)->sum('minutes_spent'),
                'active_days'     => count(array_filter($daily, fn ($d) => $d['count'] > 0)),
                'projects'        => $perProject->count(),
                'tasks_assigned'  => (int) ($tasks->assigned ?? 0),
                'tasks_done'      => (int) ($tasks->done ?? 0),
                'design_versions' => DesignVersion::query()->where('user_id', $user->id)->where('committed_at', '>=', $since)->count(),
                'current_streak'  => $current,
                'longest_streak'  => $longest,
            ],
            'projects' => $perProject->map(fn ($row) => [
                'id'       => $projects[$row->project_id]->uuid ?? null,
                'name'     => $projects[$row->project_id]->name ?? 'Project',
                'progress' => $projects[$row->project_id]->progress ?? 0,
                'updates'  => (int) $row->updates,
                'minutes'  => (int) $row->minutes,
            ])->values()->all(),
            'daily' => $daily,
        ];
    }

    // ── Internals ─────────────────────────────────────────────

    /**
     * One row per person with their output in the window.
     *
     * @param Collection<int, int> $userIds
     */
    private function memberRows(Collection $userIds, ?Project $project, Carbon $since): Collection
    {
        $updates = WorkUpdate::query()
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->where('committed_at', '>=', $since)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as updates, COALESCE(SUM(minutes_spent), 0) as minutes, MAX(committed_at) as last_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $tasks = DB::table('task_assignees')
            ->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')
            ->join('employees', 'employees.id', '=', 'task_assignees.employee_id')
            ->whereNull('tasks.deleted_at')
            ->whereNotNull('employees.user_id')
            ->when($project, fn ($q) => $q->where('tasks.project_id', $project->id))
            ->selectRaw('employees.user_id as user_id, COUNT(*) as assigned, SUM(CASE WHEN task_assignees.completed_at IS NOT NULL THEN 1 ELSE 0 END) as done')
            ->groupBy('employees.user_id')
            ->get()
            ->keyBy('user_id');

        $designs = DesignVersion::query()
            ->join('design_files', 'design_files.id', '=', 'design_versions.design_file_id')
            ->when($project, fn ($q) => $q->where('design_files.project_id', $project->id))
            ->where('design_versions.committed_at', '>=', $since)
            ->whereNotNull('design_versions.user_id')
            ->selectRaw('design_versions.user_id as user_id, COUNT(*) as versions')
            ->groupBy('design_versions.user_id')
            ->get()
            ->keyBy('user_id');

        $ids = $userIds
            ->merge($project ? $updates->keys() : collect())
            ->merge($project ? $tasks->keys() : collect())
            ->unique()
            ->values();

        return User::query()
            ->with('employee')
            ->whereIn('id', $ids)
            ->get()
            ->map(function (User $user) use ($updates, $tasks, $designs) {
                $u = $updates->get($user->id);
                $t = $tasks->get($user->id);
                $assigned = (int) ($t->assigned ?? 0);
                $done     = (int) ($t->done ?? 0);

                return [
                    'user'            => PersonPayload::make($user),
                    'updates'         => (int) ($u->updates ?? 0),
                    'minutes'         => (int) ($u->minutes ?? 0),
                    'tasks_assigned'  => $assigned,
                    'tasks_done'      => $done,
                    'completion'      => $assigned > 0 ? (int) round($done / $assigned * 100) : null,
                    'design_versions' => (int) ($designs->get($user->id)->versions ?? 0),
                    'last_activity_at' => $u->last_at ?? null,
                ];
            })
            ->sortByDesc(fn ($row) => $row['updates'] + $row['tasks_done'] * 2 + $row['design_versions'])
            ->values();
    }

    /**
     * Zero-filled per-day counts for the window.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private function daily($query, Carbon $since, int $days): array
    {
        $counts = (clone $query)
            ->where('committed_at', '>=', $since)
            ->selectRaw('DATE(committed_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(committed_at)'))
            ->pluck('total', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $since->copy()->addDays($i)->toDateString();

            $series[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $series;
    }

    /**
     * @param array<int, array{date: string, count: int}> $daily
     * @return array{0: int, 1: int} current streak, longest streak
     */
    private function streaks(array $daily): array
    {
        $longest = 0;
        $run     = 0;

        foreach ($daily as $day) {
            $run     = $day['count'] > 0 ? $run + 1 : 0;
            $longest = max($longest, $run);
        }

        // Current streak: trailing run, forgiving an empty "today".
        $current = 0;
        $i       = count($daily) - 1;

        if ($i >= 0 && $daily[$i]['count'] === 0) {
            $i--;
        }

        for (; $i >= 0 && $daily[$i]['count'] > 0; $i--) {
            $current++;
        }

        return [$current, $longest];
    }

    /**
     * @return int[]
     */
    private function projectEmployeeIds(Project $project): array
    {
        return collect([$project->owner_id, $project->project_manager_id])
            ->merge(DB::table('project_members')->where('project_id', $project->id)->pluck('employee_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
