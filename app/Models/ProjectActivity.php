<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProjectActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project_id', 'user_id', 'event', 'description', 'subject_type', 'subject_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The single audit writer for the project timeline.
     */
    public static function record(Project $project, ?User $user, string $event, string $description, ?Model $subject = null): self
    {
        return static::create([
            'project_id'   => $project->id,
            'user_id'      => $user?->id,
            'event'        => $event,
            'description'  => mb_substr($description, 0, 500),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
        ]);
    }
}