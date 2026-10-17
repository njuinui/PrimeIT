<?php

namespace App\Domain\Dashboard;

use App\Domain\Tasks\TaskScope;
use App\Http\Resources\V1\TaskResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class BuildDashboard
{
    public function __invoke(User $user, int $days = 14): array
    {
        $scope = new TaskScope($user);

        $employeeIds = $scope->employeeIds();
        $unrestricted = $employeeIds === null;
        $employee = $scope->employee();

        $team = $this->teamSection($scope, $employee);
        $company = $this->companySection($user);
        $finance = $this->financeSection($user);

        return [
            'view'           => $scope->primary(),
            'greeting_name'  => $employee?->first_name ?? $user->name,
            'today'          => now()->toIso8601String(),
            'days'           => $days,
            'activity_label' => $unrestricted ? 'Created' : 'Assigned',

            'has_team'    => $team !== null,
            'has_company' => $company !== null,
            'has_finance' => $finance !== null,

            'stats'              => $this->stats($employeeIds),
            'tasks_by_status'    => $this->tasksByStatus($employeeIds),
            'activity'           => $this->activitySeries($days, $employeeIds),
            'upcoming_deadlines' => TaskResource::collection($this->upcoming($employeeIds))->resolve(),
            'recent_tasks'       => TaskResource::collection($this->recentTasks($employeeIds))->resolve(),

            'personal' => $this->personalSection($employee),
            'team'     => $team,
            'company'  => $company,
            'finance'  => $finance,
        ];
    }

    // ── Scoped task access ─────────────────────────────────────

    /**
     * Fresh, scoped task query. Cancelled tasks never appear on dashboards.
     */
    private function scopedTasks(?array $employeeIds)
    {
        $query = Task::query()->whereNotIn('status', ['cancelled']);

        if ($employeeIds !== null) {
            $query->whereHas(
                'assignees',
                fn ($query) => $query->whereIn('task_assignees.employee_id', $employeeIds),
            );
        }

        return $query;
    }

    // ── Sections ───────────────────────────────────────────────

    private function stats(?array $employeeIds): array
    {
        $total = $this->scopedTasks($employeeIds)->count();
        $completed = $this->scopedTasks($employeeIds)->where('status', 'completed')->count();

        $dueThisWeek = $this->scopedTasks($employeeIds)
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['completed'])
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->count();

        $completedThisWeek = $this->scopedTasks($employeeIds)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();

        if ($employeeIds === null) {
            $projects = Project::count();
            $activeProjects = Project::active()->count();
            $totalBudget = (float) Project::sum('budget');
        } else {
            // Restricted scopes only count projects their tasks actually belong to.
            $projectIds = $this->scopedTasks($employeeIds)
                ->whereNotNull('project_id')
                ->distinct()
                ->pluck('project_id');

            $projects = Project::whereIn('id', $projectIds)->count();
            $activeProjects = Project::whereIn('id', $projectIds)->active()->count();
            $totalBudget = (float) Project::whereIn('id', $projectIds)->sum('budget');
        }

        return [
            'projects'            => $projects,
            'active_projects'     => $activeProjects,
            'tasks'               => $total,
            'open_tasks'          => $total - $completed,
            'completed_tasks'     => $completed,
            'completion_rate'     => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'overdue_tasks'       => $this->scopedTasks($employeeIds)->overdue()->count(),
            'due_this_week'       => $dueThisWeek,
            'completed_this_week' => $completedThisWeek,
            'total_budget'        => $totalBudget,
        ];
    }

    private function tasksByStatus(?array $employeeIds): array
    {
        return $this->scopedTasks($employeeIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function activitySeries(int $days, ?array $employeeIds): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        // Company scope: tasks created per day.
        // Restricted scope: tasks newly *assigned* within the scope per day —
        // the personal/team analogue of "created".
        if ($employeeIds === null) {
            $created = Task::query()
                ->where('created_at', '>=', $from)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
        } else {
            $created = DB::table('task_assignees as ta')
                ->join('tasks as t', 't.id', '=', 'ta.task_id')
                ->whereIn('ta.employee_id', $employeeIds)
                ->whereNotNull('ta.assigned_at')
                ->where('ta.assigned_at', '>=', $from)
                ->whereNull('t.deleted_at')
                ->whereNotIn('t.status', ['cancelled'])
                ->selectRaw('DATE(ta.assigned_at) as day, COUNT(DISTINCT t.id) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
        }

        $completed = $this->scopedTasks($employeeIds)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(function (int $i) use ($from, $created, $completed) {
                $day = $from->copy()->addDays($i)->toDateString();

                return [
                    'date'      => $day,
                    'created'   => (int) ($created[$day] ?? 0),
                    'completed' => (int) ($completed[$day] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function upcoming(?array $employeeIds)
    {
        return $this->scopedTasks($employeeIds)
            ->with(['project', 'assignees'])
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['completed'])
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->orderBy('due_date')
            ->limit(6)
            ->get();
    }

    private function recentTasks(?array $employeeIds)
    {
        return $this->scopedTasks($employeeIds)
            ->with(['project', 'assignees'])
            ->latest('id')
            ->limit(24)
            ->get();
    }

    private function personalSection(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        $own = fn () => $this->scopedTasks([$employee->id]);

        return [
            'open_tasks' => $own()->whereNotIn('status', ['completed'])->count(),
            'due_this_week' => $own()->whereNotNull('due_date')
                ->whereNotIn('status', ['completed'])
                ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                ->count(),
            'overdue' => $own()->overdue()->count(),
            'completed_this_week' => $own()->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    private function teamSection(TaskScope $scope, ?Employee $employee): ?array
    {
        if ($employee === null || ! $scope->hasDirectReports()) {
            return null;
        }

        $directReportIds = Employee::query()
            ->where('manager_id', $employee->id)
            ->pluck('id');

        // Per-member workload in a single grouped query.
        $workload = DB::table('task_assignees as ta')
            ->join('tasks as t', 't.id', '=', 'ta.task_id')
            ->whereIn('ta.employee_id', $directReportIds)
            ->whereNull('t.deleted_at')
            ->whereNotIn('t.status', ['completed', 'cancelled'])
            ->selectRaw('ta.employee_id, COUNT(DISTINCT t.id) as open_tasks, SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as overdue_tasks')
            ->groupBy('ta.employee_id')
            ->get()
            ->keyBy('employee_id');

        $members = Employee::query()
            ->whereIn('id', $directReportIds)
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $member) => [
                'id'            => $member->uuid,
                'full_name'     => trim($member->first_name.' '.$member->last_name),
                'job_title'     => $member->job_title,
                'avatar_url'    => $member->avatar ? \Illuminate\Support\Facades\Storage::disk('public')->url($member->avatar) : null,
                'open_tasks'    => (int) (($workload[$member->id] ?? null)?->open_tasks ?? 0),
                'overdue_tasks' => (int) (($workload[$member->id] ?? null)?->overdue_tasks ?? 0),
            ])
            ->values()
            ->all();

        // Totals cover the whole subtree (excluding the manager themselves).
        $teamIds = array_values(array_diff($scope->subtreeIds($employee), [$employee->id]));

        $escalation = $teamIds === []
            ? collect()
            : Task::query()
                ->with(['project', 'assignees'])
                ->whereHas('assignees', fn ($query) => $query->whereIn('task_assignees.employee_id', $teamIds))
                ->overdue()
                ->orderBy('due_date')
                ->limit(5)
                ->get();

        return [
            'members'       => $members,
            'open_tasks'    => $teamIds === [] ? 0 : $this->scopedTasks($teamIds)->whereNotIn('status', ['completed'])->count(),
            'overdue_tasks' => $teamIds === [] ? 0 : $this->scopedTasks($teamIds)->overdue()->count(),
            'escalation'    => TaskResource::collection($escalation)->resolve(),
        ];
    }

    private function companySection(User $user): ?array
    {
        if (! $user->can('reports.view.all')) {
            return null;
        }

        $departmentWorkload = DB::table('task_assignees as ta')
            ->join('employees as e', 'e.id', '=', 'ta.employee_id')
            ->join('departments as d', 'd.id', '=', 'e.department_id')
            ->join('tasks as t', 't.id', '=', 'ta.task_id')
            ->whereNull('e.deleted_at')
            ->whereNull('d.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereNotIn('t.status', ['completed', 'cancelled'])
            ->selectRaw("d.name as department, COUNT(DISTINCT e.id) as employees, COUNT(DISTINCT t.id) as open_tasks, SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as overdue")
            ->groupBy('d.id', 'd.name')
            ->orderByDesc('open_tasks')
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department,
                'employees'  => (int) $row->employees,
                'open_tasks' => (int) $row->open_tasks,
                'overdue'    => (int) $row->overdue,
            ])
            ->all();

        $projectProgress = Project::query()
            ->active()
            ->withCount(['tasks as open_tasks' => fn ($query) => $query->whereNotIn('tasks.status', ['completed', 'cancelled'])])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(fn (Project $project) => [
                'id'         => $project->uuid,
                'name'       => $project->name,
                'status'     => $project->status,
                'priority'   => $project->priority,
                'progress'   => (int) $project->progress,
                'due_date'   => $project->due_date?->toDateString(),
                'open_tasks' => (int) $project->open_tasks,
                'budget'     => (float) $project->budget,
                'currency'   => $project->currency,
            ])
            ->all();

        return [
            'department_workload' => $departmentWorkload,
            'project_progress'    => $projectProgress,
            'throughput'          => [
                'created_this_week'   => Task::where('created_at', '>=', now()->subDays(7))->count(),
                'completed_this_week' => Task::whereNotNull('completed_at')->where('completed_at', '>=', now()->subDays(7))->count(),
            ],
        ];
    }

    private function financeSection(User $user): ?array
    {
        if (! $user->can('finance.view.all')) {
            return null;
        }

        $budgetByDepartment = DB::table('projects as p')
            ->leftJoin('departments as d', 'd.id', '=', 'p.department_id')
            ->whereNull('p.deleted_at')
            ->whereNotIn('p.status', ['cancelled'])
            ->selectRaw("COALESCE(d.name, 'Unassigned') as department, COUNT(*) as projects, COALESCE(SUM(p.budget), 0) as budget")
            ->groupBy('d.id', 'd.name')
            ->orderByDesc('budget')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department,
                'projects'   => (int) $row->projects,
                'budget'     => (float) $row->budget,
            ])
            ->all();

        return [
            'currency'             => 'XAF',
            'total_budget'         => (float) Project::whereNotIn('status', ['cancelled'])->sum('budget'),
            'budget_by_department' => $budgetByDepartment,
        ];
    }
}