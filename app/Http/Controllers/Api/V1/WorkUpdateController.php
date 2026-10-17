<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Work\RecordWork;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WorkUpdateResource;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkUpdateController extends Controller
{
    /** The project's work feed — newest first. */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($request->user()->can('work.view'), 403);

        $query = WorkUpdate::query()
            ->where('project_id', $project->id)
            ->with(['user.employee', 'task:id,uuid,task_number,title', 'designFile:id,uuid,title,provider'])
            ->orderByDesc('committed_at')
            ->orderByDesc('id');

        if ($request->filled('user')) {
            $author = User::query()->where('uuid', (string) $request->query('user'))->first();
            $query->where('user_id', $author?->id ?? 0);
        }

        if ($request->filled('kind')) {
            $query->where('kind', (string) $request->query('kind'));
        }

        return $this->paginated(
            $query->paginate(min((int) $request->query('per_page', 20), 50)),
            WorkUpdateResource::class,
        );
    }

    /** Log a piece of work — the "commit". */
    public function store(Request $request, Project $project, RecordWork $record): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('work.log'), 403);
        abort_unless($user->can('projects.view.all') || $project->isParticipant($user), 403, 'Only project participants can log work.');

        $data = $request->validate([
            'message'       => ['required', 'string', 'max:255'],
            'body'          => ['nullable', 'string', 'max:5000'],
            'kind'          => ['nullable', 'in:commit,review,note'],
            'task_id'       => ['nullable', 'uuid'],
            'minutes_spent' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'progress'      => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $update = $record->commit($project, $user, $data)->load(['user.employee', 'task:id,uuid,task_number,title']);

        return $this->success(new WorkUpdateResource($update), 'Work logged.', 201);
    }
}
