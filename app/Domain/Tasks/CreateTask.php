<?php

namespace App\Domain\Tasks;

use App\Events\TaskCreated;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTask
{
    public function __construct(private readonly TaskNumber $numbers) {}

    /**
     * @param array<string, mixed> $data         validated task attributes
     * @param array<int, int>      $assigneeIds  employee ids to attach
     */
    public function __invoke(array $data, array $assigneeIds, User $actor): Task
    {
        $task = DB::transaction(function () use ($data, $assigneeIds, $actor): Task {
            $task = Task::create([
                ...$data,
                'task_number' => $this->numbers->next(),
                'slug'        => Str::slug((string) $data['title']).'-'.Str::lower(Str::random(4)),
                'created_by'  => $actor->id,
            ]);

            $watcherIds = [$actor->id];

            if ($assigneeIds !== []) {
                $task->assignees()->attach(collect($assigneeIds)->mapWithKeys(
                    fn (int $id) => [$id => ['assigned_by' => $actor->id, 'assigned_at' => now()]]
                )->all());

                $watcherIds = array_merge(
                    $watcherIds,
                    Employee::query()->whereIn('id', $assigneeIds)->pluck('user_id')->filter()->all(),
                );
            }

            $task->watchers()->attach(array_values(array_unique($watcherIds)));

            return $task;
        });

        event(new TaskCreated($task, $actor));

        if ($task->project_id !== null) {
            ProjectActivity::record(
                Project::query()->find($task->project_id),
                $actor,
                'task_created',
                'created task "'.$task->title.'"',
                $task,
            );
        }

        return $task;
    }
}