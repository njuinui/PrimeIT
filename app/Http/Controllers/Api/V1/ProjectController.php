<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Projects\ProjectNumber;
use App\Domain\Projects\RecalculateProjectProgress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\V1\ProjectResource;
use App\Http\Resources\V1\TaskResource;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectNumber $projectNumber,
        private readonly RecalculateProjectProgress $recalculateProgress,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('projects.view');

        $search      = trim((string) $request->query('search'));
        $status      = (string) $request->query('status');
        $priority    = (string) $request->query('priority');
        $department  = $this->uuidToId(Department::class, (string) $request->query('department'));
        $mine        = $request->boolean('mine');

        $query = Project::query()
            ->accessibleBy($request->user())
            ->with(['owner', 'projectManager', 'department'])
            ->withCount(['members', 'milestones', 'risks'])
            ->withCount('tasks as total_tasks')
            ->withCount(['tasks as open_tasks' => fn ($q) => $q->whereNotIn('tasks.status', ['completed', 'cancelled'])])
            ->withCount(['tasks as completed_tasks' => fn ($q) => $q->where('tasks.status', 'completed')])
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('project_number', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->when($department !== null, fn ($q) => $q->where('department_id', $department))
            ->when($mine, fn ($q) => $q->where(function ($inner) use ($request) {
                $employee = $request->user()->employee()->first();
                if ($employee === null) {
                    return $inner->whereRaw('1 = 0');
                }
                $inner->where('owner_id', $employee->id)
                    ->orWhere('project_manager_id', $employee->id)
                    ->orWhereHas('members', fn ($m) => $m->where('project_members.employee_id', $employee->id));
            }))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date');

        $projects = $query->paginate(min((int) $request->query('per_page', 12), 50));

        return $this->paginated($projects, ProjectResource::class);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('projects.create');

        $data = collect($request->validated());

        $slug = Str::slug((string) $data['name']);

        if (Project::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(5));
        }

        $project = Project::create([
            ...$data->except(['currency'])->all(),
            'project_number' => $this->projectNumber->next(),
            'slug'           => $slug,
            'currency'       => strtoupper((string) $data->get('currency', 'XAF')),
            'created_by'     => $request->user()->id,
        ]);

        ProjectActivity::record(
            $project,
            $request->user(),
            'project_created',
            'created the project',
            $project,
        );

        $project->loadMissing(['owner', 'projectManager', 'department'])->loadCount(['members', 'milestones', 'risks']);

        return $this->success(new ProjectResource($project), 'Project created.', 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->loadMissing(['owner', 'projectManager', 'department', 'members.department'])
            ->loadCount(['milestones', 'risks']);

        $project->total_tasks     = $project->tasks()->count();
        $project->open_tasks      = $project->tasks()->whereNotIn('status', ['completed', 'cancelled'])->count();
        $project->completed_tasks = $project->tasks()->where('status', 'completed')->count();

        return $this->success(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = collect($request->validated());

        $newStatus = (string) $data->get('status', $project->status);

        // Lifecycle transitions.
        if ($newStatus === Project::STATUS_COMPLETED && $project->status !== Project::STATUS_COMPLETED) {
            $data['completed_at'] = now();
        } elseif ($newStatus !== Project::STATUS_COMPLETED && $project->status === Project::STATUS_COMPLETED) {
            $data['completed_at'] = null;
        }

        if ($data->has('currency') && $data['currency'] !== null) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        $oldStatus = $project->status;

        $project->update($data->all());

        if ($newStatus === Project::STATUS_COMPLETED && $oldStatus !== Project::STATUS_COMPLETED) {
            ($this->recalculateProgress)($project);
        }

        if ($newStatus !== $oldStatus) {
            ProjectActivity::record(
                $project,
                $request->user(),
                'status_changed',
                sprintf('moved the project from %s to %s', str_replace('_', ' ', $oldStatus), str_replace('_', ' ', $newStatus)),
                $project,
            );
        } else {
            ProjectActivity::record($project, $request->user(), 'project_updated', 'updated the project details', $project);
        }

        $project = $project->fresh();
        $project->loadMissing(['owner', 'projectManager', 'department', 'members.department'])->loadCount(['milestones', 'risks']);
        $project->total_tasks     = $project->tasks()->count();
        $project->open_tasks      = $project->tasks()->whereNotIn('status', ['completed', 'cancelled'])->count();
        $project->completed_tasks = $project->tasks()->where('status', 'completed')->count();

        return $this->success(new ProjectResource($project), 'Project updated.');
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return $this->success(message: 'Project deleted.');
    }

    /**
     * Lightweight task list for the project detail page — the full
     * task module with Kanban arrives in Phase 7.
     */
    public function tasks(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $status = (string) $request->query('status');

        $tasks = $project->tasks()
            ->with(['project', 'assignees'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->paginated($tasks, TaskResource::class);
    }

    private function uuidToId(string $model, string $uuid): ?int
    {
        if ($uuid === '') {
            return null;
        }

        return $model::query()->where('uuid', $uuid)->value('id');
    }
}