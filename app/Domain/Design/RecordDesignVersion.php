<?php

namespace App\Domain\Design;

use App\Domain\Work\RecordWork;
use App\Models\DesignFile;
use App\Models\DesignVersion;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logs a design version ("commit" for a design file), moves the file's
 * progress/status, and mirrors the version into the work feed.
 */
final class RecordDesignVersion
{
    public function __construct(private readonly RecordWork $work) {}

    /**
     * @param array{label: string, note?: ?string, progress?: ?int, status?: ?string,
     *              external_version_id?: ?string, external_author?: ?string,
     *              thumbnail_url?: ?string, committed_at?: mixed} $data
     */
    public function __invoke(DesignFile $file, ?User $actor, array $data): DesignVersion
    {
        return DB::transaction(function () use ($file, $actor, $data): DesignVersion {
            $progressBefore = $file->progress;

            $committedAt = isset($data['committed_at']) ? Carbon::parse($data['committed_at']) : now();

            $version = $file->versions()->create([
                'user_id'             => $actor?->id,
                'label'               => mb_substr($data['label'], 0, 120),
                'note'                => $data['note'] ?? null,
                'progress'            => $data['progress'] ?? null,
                'status'              => $data['status'] ?? null,
                'external_version_id' => $data['external_version_id'] ?? null,
                'external_author'     => $data['external_author'] ?? null,
                'thumbnail_url'       => $data['thumbnail_url'] ?? null,
                'committed_at'        => $committedAt,
            ]);

            $changes = [
                'last_activity_at' => $file->last_activity_at !== null && $file->last_activity_at->gt($committedAt)
                    ? $file->last_activity_at
                    : $committedAt,
            ];

            if (isset($data['progress'])) {
                $changes['progress'] = (int) $data['progress'];
            }

            if (! empty($data['status'])) {
                $changes['status'] = $data['status'];
            }

            $file->forceFill($changes)->save();

            $this->work->design($file->refresh(), $actor, $version, $progressBefore);

            if ($actor !== null) {
                ProjectActivity::record(
                    Project::query()->findOrFail($file->project_id),
                    $actor,
                    'design_version',
                    sprintf('saved "%s" on design "%s"', $version->label, $file->title),
                );
            }

            return $version;
        });
    }
}
