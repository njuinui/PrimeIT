<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tasks\CreateTask;
use App\Domain\Tasks\TaskAccess;
use App\Domain\Tasks\UpdateTask;
use App\Events\Realtime\TaskBoardUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskStatusRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Reminder;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private readonly CreateTask $createTask,
        private readonly UpdateTask $updateTask,
        private readonly TaskAccess $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('tasks.view');

        $user       = $request->user();
        $search     = trim((string) $request->query('search'));
        $status     = (string) $request->query('status');
        $priority   = (string) $request->query('priority');
        $projectId  = $this->uuidToId(Project::class, (string) $request->query('project'));
        $assigneeId = $this->uuidToId(Employee::class, (string) $request->query('assignee'));
        $due        = (string) $request->query('due');
        $dueFrom    = (string) $request->query('due_from');
        $dueTo      = (string) $request->query('due_to');
        $mine       = $request->boolean('mine');
        $watching   = $request->boolean('watching');

        $tasks = Task::query()
            ->accessibleBy($user)
            ->with(['project', 'assignees'])
            ->withCount([
                'checklistItems as checklist_total',
                'checklistItems as checklist_completed' => fn ($q) => $q->where('is_completed', true),
                'comments as comments_count',
            ])
            ->withSum('timeEntries as total_minutes', 'minutes')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('task_number', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->when($assigneeId !== null, fn ($q) => $q->whereHas(
                'assignees',
                fn ($inner) => $inner->where('task_assignees.employee_id', $assigneeId),
            ))
            ->when($due === 'overdue', fn ($q) => $q->overdue())
            ->when($due === 'today', fn ($q) => $q->whereNotNull('due_date')
                ->whereBetween('due_date', [now()->startOfDay(), now()->endOfDay()]))
            ->when($due === 'week', fn ($q) => $q->dueWithin(7))
            ->when($due === 'no_date', fn ($q) => $q->whereNull('due_date'))
            ->when($dueFrom !== '' && strtotime($dueFrom) !== false,
                fn ($q) => $q->whereDate('due_date', '>=', $dueFrom))
            ->when($dueTo !== '' && strtotime($dueTo) !== false,
                fn ($q) => $q->whereDate('due_date', '<=', $dueTo))
            ->when($mine, fn ($q) => $q->where(function ($inner) use ($user) {
                $employee = $user->employee()->first();

                $inner->where('created_by', $user->id);

                if ($employee !== null) {
                    $inner->orWhereHas(
                        'assignees',
                        fn ($a) => $a->where('task_assignees.employee_id', $employee->id),
                    );
                }
            }))
            ->when($watching, fn ($q) => $q->whereHas(
                'watchers',
                fn ($inner) => $inner->where('task_watchers.user_id', $user->id),
            ))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->paginated($tasks, TaskResource::class);
    }

    /**
     * Lightweight picker (uuid + task_number + title + status) for
     * dependency selectors and other task-picking UIs.
     */
    public function options(Request $request): JsonResponse
    {
        $this->authorize('tasks.view');

        $projectUuid = (string) $request->query('project');
        $user        = $request->user();

        $query = Task::query();

        if ($projectUuid !== '') {
            $project = Project::query()->where('uuid', $projectUuid)->first();

            if ($project === null) {
                return $this->error('Project not found.', 404);
            }

            if ($user->cannot('view', $project)) {
                return $this->error('You do not have access to this project.', 403);
            }

            $query->where('project_id', $project->id);
        } else {
            $query->accessibleBy($user);
        }

        $tasks = $query
            ->orderBy('title')
            ->limit(100)
            ->get(['id', 'uuid', 'task_number', 'title', 'status']);

        return $this->success(
            $tasks->map(fn (Task $task) => [
                'id'          => $task->uuid,
                'task_number' => $task->task_number,
                'title'       => $task->title,
                'status'      => $task->status,
            ])->all()
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('tasks.create');

        $user = $request->user();
        $data = collect($request->validated())->except('assignees')->all();

        if (isset($data['project_id']) && $data['project_id'] !== null) {
            $project = Project::query()->findOrFail($data['project_id']);
            $this->authorize('view', $project);
        }

        $assigneeUuids = $request->validated('assignees', []) ?? [];
        $assigneeIds   = $assigneeUuids !== []
            ? Employee::query()->whereIn('uuid', $assigneeUuids)->pluck('id')->values()->all()
            : [];

        if (
            $assigneeIds !== []
            && ! $this->access->canAssignInProject($user, $data['project_id'] ?? null, $assigneeIds)
        ) {
            return $this->error('You are not allowed to assign tasks to other people in this context.', 403);
        }

        $task = ($this->createTask)($data, $assigneeIds, $user);

        $task->loadMissing(['project', 'assignees']);

        return $this->success(new TaskResource($task), 'Task created.', 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->loadMissing([
            'project',
            'assignees',
            'checklistItems',
            'dependencies',
            'dependents',
            'timeEntries.user',
            'watchers',
        ])->loadCount([
            'comments as comments_count',
            'checklistItems as checklist_total',
            'checklistItems as checklist_completed' => fn ($q) => $q->where('is_completed', true),
        ])->loadSum('timeEntries as total_minutes', 'minutes');

        return $this->success(new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = ($this->updateTask)($task, $request->validated(), $request->user());

        $task->loadMissing(['project', 'assignees']);

        return $this->success(new TaskResource($task), 'Task updated.');
    }

    public function status(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = ($this->updateTask)($task, ['status' => $request->validated('status')], $request->user());

        $task->loadMissing(['project', 'assignees']);

        return $this->success(new TaskResource($task), 'Task status updated.');
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        if ($task->project_id !== null) {
            ProjectActivity::record(
                Project::query()->find($task->project_id),
                $request->user(),
                'task_deleted',
                'deleted task "' . $task->title . '"',
            );
        }

        Reminder::cancelForRemindable($task);

        $uuid        = $task->uuid;
        $taskNumber  = $task->task_number;

        $task->delete();

        broadcast(new TaskBoardUpdated('task.deleted', $uuid, $taskNumber, null));

        return $this->success(message: 'Task deleted.');
    }

    public function watch(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $userId  = $request->user()->id;
        $watched = $task->watchers()->where('task_watchers.user_id', $userId)->exists();

        if ($watched) {
            $task->watchers()->detach($userId);
        } else {
            $task->watchers()->attach($userId);
        }

        return $this->success([
            'watching'       => ! $watched,
            'watchers_count' => $task->watchers()->count(),
        ]);
    }

    private function uuidToId(string $model, string $uuid): ?int
    {
        if ($uuid === '') {
            return null;
        }

        return $model::query()->where('uuid', $uuid)->value('id');
    }
}