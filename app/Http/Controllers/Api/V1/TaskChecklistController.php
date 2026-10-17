<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tasks\RecalculateTaskProgress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreChecklistItemRequest;
use App\Http\Requests\Tasks\UpdateChecklistItemRequest;
use App\Http\Resources\V1\ChecklistItemResource;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskChecklistController extends Controller
{
    public function __construct(private readonly RecalculateTaskProgress $recalculate) {}

    public function store(StoreChecklistItemRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $item = $task->checklistItems()->create([
            'content'    => $request->validated('content'),
            'sort_order' => ((int) $task->checklistItems()->max('sort_order')) + 1,
            'created_by' => $request->user()->id,
        ]);

        $task = ($this->recalculate)($task);

        return $this->success([
            'item'           => new ChecklistItemResource($item),
            'task_progress'  => (int) $task->progress,
        ], 'Checklist item added.', 201);
    }

    public function update(UpdateChecklistItemRequest $request, Task $task, TaskChecklistItem $item): JsonResponse
    {
        $this->authorize('update', $task);
        $this->assertBelongsTo($task, $item);

        $data = ['content' => $request->validated('content')];

        if ($request->has('is_completed')) {
            $data['is_completed'] = (bool) $request->validated('is_completed');
            $data['completed_at'] = $data['is_completed'] ? now() : null;
        }

        $item->update($data);

        $task = ($this->recalculate)($task);

        return $this->success([
            'item'          => new ChecklistItemResource($item->fresh()),
            'task_progress' => (int) $task->progress,
        ]);
    }

    public function toggle(Request $request, Task $task, TaskChecklistItem $item): JsonResponse
    {
        $this->authorize('update', $task);
        $this->assertBelongsTo($task, $item);

        $completing = ! $item->is_completed;

        $item->forceFill([
            'is_completed' => $completing,
            'completed_at' => $completing ? now() : null,
        ])->save();

        $task = ($this->recalculate)($task);

        return $this->success([
            'item'          => new ChecklistItemResource($item->fresh()),
            'task_progress' => (int) $task->progress,
        ]);
    }

    public function destroy(Request $request, Task $task, TaskChecklistItem $item): JsonResponse
    {
        $this->authorize('update', $task);
        $this->assertBelongsTo($task, $item);

        $item->delete();

        $task = ($this->recalculate)($task);

        return $this->success(['task_progress' => (int) $task->progress], 'Checklist item removed.');
    }

    private function assertBelongsTo(Task $task, TaskChecklistItem $item): void
    {
        abort_unless($item->task_id === $task->id, 404);
    }
}