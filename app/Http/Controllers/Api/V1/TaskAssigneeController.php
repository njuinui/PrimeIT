<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tasks\AttachAssignees;
use App\Domain\Tasks\DetachAssignee;
use App\Domain\Tasks\TaskAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskAssigneesRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Employee;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskAssigneeController extends Controller
{
    public function __construct(
        private readonly TaskAccess $access,
        private readonly AttachAssignees $attachAssignees,
        private readonly DetachAssignee $detachAssignee,
    ) {}

    public function store(StoreTaskAssigneesRequest $request, Task $task): JsonResponse
    {
        $user = $request->user();

        $employeeIds = Employee::query()
            ->whereIn('uuid', $request->validated('employees'))
            ->pluck('id')
            ->values()
            ->all();

        $own       = $user->employee()->first();
        $selfOnly  = $own !== null && $employeeIds === [$own->id];

        if (! $selfOnly && ! $this->access->canAssign($user, $task)) {
            return $this->error('You are not allowed to assign people to this task.', 403);
        }

        $added = ($this->attachAssignees)($task, $employeeIds, $user);

        $task = $task->fresh(['project', 'assignees']);

        return $this->success(
            new TaskResource($task),
            $added->isEmpty() ? 'No new assignees to add.' : sprintf('%d assignee(s) added.', $added->count()),
        );
    }

    public function destroy(Request $request, Task $task, Employee $employee): JsonResponse
    {
        $user = $request->user();
        $own  = $user->employee()->first();

        $isSelf = $own !== null && $own->id === $employee->id;

        if (! $isSelf && ! $this->access->canAssign($user, $task)) {
            return $this->error('You are not allowed to remove assignees from this task.', 403);
        }

        if (! $task->assignees()->where('employees.id', $employee->id)->exists()) {
            return $this->error('This employee is not assigned to the task.', 404);
        }

        ($this->detachAssignee)($task, $employee, $user);

        return $this->success(new TaskResource($task->fresh(['project', 'assignees'])), 'Assignee removed.');
    }
}