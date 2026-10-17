<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTimeEntryRequest;
use App\Http\Resources\V1\TimeEntryResource;
use App\Models\Task;
use App\Models\TaskTimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskTimeEntryController extends Controller
{
    public function store(StoreTimeEntryRequest $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task); // anyone who can see the task may log their own time

        $entry = $task->timeEntries()->create([
            'user_id'   => $request->user()->id,
            'minutes'   => $request->validated('minutes'),
            'note'      => $request->validated('note'),
            'worked_at' => $request->validated('worked_at', now()->toDateString()),
        ]);

        $task->forceFill(['actual_minutes' => (int) $task->timeEntries()->sum('minutes')])->save();

        return $this->success([
            'entry'         => new TimeEntryResource($entry->loadMissing('user')),
            'total_minutes' => (int) $task->timeEntries()->sum('minutes'),
        ], 'Time logged.', 201);
    }

    public function destroy(Request $request, Task $task, TaskTimeEntry $entry): JsonResponse
    {
        $this->authorize('view', $task);
        $this->assertBelongsTo($task, $entry);

        if ($entry->user_id !== $request->user()->id) {
            return $this->error('You can only delete your own time entries.', 403);
        }

        $entry->delete();

        $task->forceFill(['actual_minutes' => (int) $task->timeEntries()->sum('minutes')])->save();

        return $this->success(message: 'Time entry removed.');
    }

    private function assertBelongsTo(Task $task, TaskTimeEntry $entry): void
    {
        abort_unless($entry->task_id === $task->id, 404);
    }
}