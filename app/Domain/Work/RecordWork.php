<?php

namespace App\Domain\Work;

use App\Events\Realtime\ProjectWorkLogged;
use App\Models\DesignFile;
use App\Models\DesignVersion;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkUpdate;

/**
 * The single writer for work updates ("commits"). Everything that should
 * show up on the contribution graph goes through here.
 */
final class RecordWork
{
    /**
     * A manual log entry written by a person.
     *
     * @param array{message: string, body?: ?string, kind?: string, task_id?: ?string, minutes_spent?: ?int, progress?: ?int} $data
     */
    public function commit(Project $project, User $actor, array $data): WorkUpdate
    {
        $task = null;

        if (! empty($data['task_id'])) {
            $task = Task::query()
                ->where('uuid', $data['task_id'])
                ->where('project_id', $project->id)
                ->firstOrFail();
        }

        $from = $task?->progress;
        $to   = isset($data['progress']) ? (int) $data['progress'] : null;

        // A checklist drives task progress; otherwise the logged value does.
        if ($task !== null && $to !== null && $task->status !== 'completed' && ! $task->checklistItems()->exists()) {
            $task->forceFill(['progress' => $to])->save();
        } else {
            $to = $task !== null && $to !== null ? $task->progress : null;
        }

        $update = WorkUpdate::create([
            'project_id'    => $project->id,
            'task_id'       => $task?->id,
            'user_id'       => $actor->id,
            'kind'          => $data['kind'] ?? 'commit',
            'source'        => 'manual',
            'message'       => mb_substr($data['message'], 0, 255),
            'body'          => $data['body'] ?? null,
            'progress_from' => $to !== null ? $from : null,
            'progress_to'   => $to,
            'minutes_spent' => (int) ($data['minutes_spent'] ?? 0),
            'committed_at'  => now(),
        ]);

        ProjectActivity::record($project, $actor, 'work_logged', 'logged work: ' . $update->message, $task);

        event(new ProjectWorkLogged($project->uuid, $update->kind));

        return $update;
    }

    /**
     * A system-generated entry (e.g. a task changed status).
     */
    public function system(Project $project, ?User $actor, ?Task $task, string $message): WorkUpdate
    {
        $update = WorkUpdate::create([
            'project_id'   => $project->id,
            'task_id'      => $task?->id,
            'user_id'      => $actor?->id,
            'kind'         => 'status',
            'source'       => 'system',
            'message'      => mb_substr($message, 0, 255),
            'committed_at' => now(),
        ]);

        event(new ProjectWorkLogged($project->uuid, 'status'));

        return $update;
    }

    /**
     * A design version, mirrored into the work feed so it counts as activity.
     */
    public function design(DesignFile $file, ?User $actor, DesignVersion $version, ?int $progressBefore): WorkUpdate
    {
        $project = Project::query()->findOrFail($file->project_id);

        $update = WorkUpdate::create([
            'project_id'     => $project->id,
            'task_id'        => $file->task_id,
            'design_file_id' => $file->id,
            'user_id'        => $actor?->id,
            'kind'           => 'design',
            'source'         => $file->provider,
            'message'        => mb_substr($file->title . ' — ' . $version->label, 0, 255),
            'body'           => $version->note,
            'progress_from'  => $progressBefore,
            'progress_to'    => $file->progress,
            'committed_at'   => $version->committed_at,
        ]);

        event(new ProjectWorkLogged($project->uuid, 'design'));

        return $update;
    }
}
