<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const STATUSES = ['scheduled', 'completed', 'cancelled'];

    protected $fillable = [
        'title', 'description', 'location', 'video_link',
        'starts_at', 'ends_at', 'status', 'organized_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'organized_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'meeting_participants')
            ->using(MeetingParticipant::class)
            ->withPivot(['attendance', 'response', 'responded_at', 'added_by'])
            ->withTimestamps()
            ->orderBy('first_name');
    }

    public function minutes(): HasOne
    {
        return $this->hasOne(MeetingMinutes::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')->where('starts_at', '>', now());
    }

    public function scopePast($query)
    {
        return $query->where(function ($inner) {
            $inner->where('starts_at', '<', now())
                ->orWhereIn('status', ['completed', 'cancelled']);
        });
    }

    public function isHappening(): bool
    {
        return $this->status === 'scheduled'
            && $this->starts_at->lte(now())
            && $this->ends_at->gt(now());
    }

    /** Participant employee ids (organizer included when participating). */
    public function participantEmployeeIds(): array
    {
        return $this->participants()->pluck('employees.id')->all();
    }
}