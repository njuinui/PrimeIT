<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tracked design / prototype file living in Figma, Adobe XD, Sketch…
 * The file itself stays in the design tool; PrimeIT tracks its progress.
 */
class DesignFile extends Model
{
    use HasPublicUuid, SoftDeletes;

    public const PROVIDERS = ['figma', 'adobe_xd', 'sketch', 'other'];
    public const STATUSES  = ['draft', 'in_review', 'changes_requested', 'approved', 'handed_off'];

    protected $fillable = [
        'project_id', 'task_id', 'owner_id', 'provider', 'title', 'url', 'external_key',
        'status', 'progress', 'last_activity_at', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'progress'         => 'integer',
            'last_activity_at' => 'datetime',
            'last_synced_at'   => 'datetime',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DesignVersion::class)->orderByDesc('committed_at')->orderByDesc('id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DesignVersion::class)->latestOfMany('committed_at');
    }

    /**
     * Pull the file key out of a Figma URL (figma.com/file|design|proto/<key>/…).
     */
    public static function figmaKeyFromUrl(string $url): ?string
    {
        if (preg_match('~figma\.com/(?:file|design|proto|board)/([A-Za-z0-9]+)~', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
