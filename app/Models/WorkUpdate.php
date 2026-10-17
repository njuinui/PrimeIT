<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "commit" for project work: who did what, where, for how long.
 */
class WorkUpdate extends Model
{
    use HasPublicUuid;

    public const KINDS   = ['commit', 'status', 'review', 'design', 'note'];
    public const SOURCES = ['manual', 'system', 'figma', 'adobe_xd', 'sketch', 'other'];

    protected $fillable = [
        'project_id', 'task_id', 'design_file_id', 'user_id',
        'kind', 'source', 'message', 'body',
        'progress_from', 'progress_to', 'minutes_spent', 'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_from' => 'integer',
            'progress_to'   => 'integer',
            'minutes_spent' => 'integer',
            'committed_at'  => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function designFile(): BelongsTo
    {
        return $this->belongsTo(DesignFile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
