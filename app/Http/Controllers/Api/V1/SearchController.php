<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Support\PersonPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search box. Every group respects the same visibility rules as the
 * pages it links to — a person never sees a hit they could not open.
 */
class SearchController extends Controller
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = trim((string) $request->query('q', ''));

        $empty = ['projects' => [], 'tasks' => [], 'people' => []];

        if (mb_strlen($term) < 2) {
            return $this->success($empty);
        }

        $like = '%' . addcslashes($term, '\\%_') . '%';

        $projects = $user->can('projects.view')
            ? Project::query()
                ->accessibleBy($user)
                ->where('name', 'like', $like)
                ->orderBy('name')
                ->limit(self::LIMIT)
                ->get(['id', 'uuid', 'name', 'status'])
                ->map(fn (Project $p) => ['id' => $p->uuid, 'name' => $p->name, 'status' => $p->status])
                ->all()
            : [];

        $tasks = $user->can('tasks.view')
            ? Task::query()
                ->accessibleBy($user)
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('task_number', 'like', $like))
                ->with('project:id,uuid,name')
                ->latest('id')
                ->limit(self::LIMIT)
                ->get(['id', 'uuid', 'task_number', 'title', 'status', 'project_id'])
                ->map(fn (Task $t) => [
                    'id'          => $t->uuid,
                    'task_number' => $t->task_number,
                    'title'       => $t->title,
                    'status'      => $t->status,
                    'project'     => $t->project?->name,
                ])
                ->all()
            : [];

        $people = $user->can('employees.view')
            ? Employee::query()
                ->active()
                ->with('user.employee')
                ->where(fn ($q) => $q
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orderBy('first_name')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (Employee $e) => [
                    'id'         => $e->uuid,
                    'name'       => $e->full_name,
                    'job_title'  => $e->job_title,
                    'avatar_url' => PersonPayload::make($e->user)['avatar_url'] ?? null,
                ])
                ->all()
            : [];

        return $this->success(compact('projects', 'tasks', 'people'));
    }
}
