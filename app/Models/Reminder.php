<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Reminder extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_SENT      = 'sent';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'user_id',
        'remindable_type',
        'remindable_id',
        'type',
        'scheduled_for',
        'channel',
        'status',
        'sent_at',
        'failed_at',
        'failure_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Reminder $reminder): void {
            if (empty($reminder->uuid)) {
                $reminder->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at'       => 'datetime',
            'failed_at'     => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->pending()->where('scheduled_for', '<=', now());
    }

    public function scopeForRemindable(Builder $query, Model $remindable): Builder
    {
        return $query
            ->where('remindable_type', $remindable->getMorphClass())
            ->where('remindable_id', $remindable->getKey());
    }

    // ── Helpers ───────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Cancel every pending reminder attached to a model (task completed,
     * cancelled, deleted, or its due date moved). Returns the number cancelled.
     */
    public static function cancelForRemindable(Model $remindable): int
    {
        return static::query()
            ->forRemindable($remindable)
            ->pending()
            ->update(['status' => self::STATUS_CANCELLED]);
    }
}