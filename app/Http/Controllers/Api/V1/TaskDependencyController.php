<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreDependencyRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskDependencyController extends Controller
{
    public function store(StoreDependencyRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $dependsOn = Task::query()->where('uuid', $request->validated('depends_on_task_id'))->firstOrFail();

        if ($dependsOn->id === $task->id) {
            return $this->error('A task cannot depend on itself.', 422);
        }

        if ($task->dependencies()->where('tasks.id', $dependsOn->id)->exists()) {
            return $this->error('This dependency already exists.', 409);
        }

        if ($this->createsCycle($task, $dependsOn)) {
            return $this->error('Adding this dependency would create a circular chain.', 422);
        }

        $task->dependencies()->attach($dependsOn->id, ['created_by' => $request->user()->id]);

        return $this->success($this->dependencyList($task), 'Dependency added.', 201);
    }

    public function destroy(Request $request, Task $task, Task $dependsOnTask): JsonResponse
    {
        $this->authorize('update', $task);

        $task->dependencies()->detach($dependsOnTask->id);

        return $this->success($this->dependencyList($task), 'Dependency removed.');
    }

    private function dependencyList(Task $task): array
    {
        return $task->dependencies()->get()->map(fn ($dep) => [
            'id'          => $dep->uuid,
            'task_number' => $dep->task_number,
            'title'       => $dep->title,
            'status'      => $dep->status,
        ])->all();
    }

    /**
     * Walks the depends-on graph forward from $dependsOn — if we can
     * reach $task again, attaching would create a cycle.
     */
    private function createsCycle(Task $task, Task $dependsOn): bool
    {
        $frontier = [$dependsOn->id];
        $seen     = [];

        while ($frontier !== []) {
            $next = DB::table('task_dependencies')
                ->whereIn('task_id', $frontier)
                ->pluck('depends_on_task_id')
                ->diff($seen)
                ->values()
                ->all();

            if (in_array($task->id, $next, true)) {
                return true;
            }

            $seen     = array_merge($seen, $frontier);
            $frontier = $next;
        }

        return false;
    }
}