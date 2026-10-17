<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const STATUSES   = ['backlog', 'todo', 'in_progress', 'blocked', 'review', 'completed', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'task_number',
        'project_id',
        'parent_task_id',
        'title',
        'slug',
        'description',
        'status',
        'priority',
        'created_by',
        'start_date',
        'due_date',
        'completed_at',
        'estimated_minutes',
        'actual_minutes',
        'progress',
        'sort_order',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'start_date'        => 'datetime',
            'due_date'          => 'datetime',
            'completed_at'      => 'datetime',
            'estimated_minutes' => 'integer',
            'actual_minutes'    => 'integer',
            'progress'          => 'integer',
            'sort_order'        => 'integer',
            'is_recurring'      => 'boolean',
        ];
    }

    // ── Relations ─────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_assignees')
            ->using(TaskAssignee::class)
            ->withPivot(['assigned_by', 'assigned_at', 'accepted_at', 'declined_at', 'decline_reason', 'completed_at'])
            ->withTimestamps();
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest('id');
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')->withTimestamps();
    }

    /** Tasks this task is blocked by. */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot(['created_by'])
            ->withTimestamps();
    }

    /** Tasks blocked by this task. */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot(['created_by'])
            ->withTimestamps();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class)->latest('id');
    }

    // ── Scoping & helpers ─────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }

    /**
     * Tasks the user may see: everything with tasks.view.all, otherwise
     * assigned-to-me ∨ created-by-me ∨ belonging to a project I participate in.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->can('tasks.view.all')) {
            return $query;
        }

        $employee = $user->employee()->first();

        return $query->where(function (Builder $inner) use ($user, $employee) {
            $inner->where('created_by', $user->id);

            if ($employee !== null) {
                $inner->orWhereHas(
                    'assignees',
                    fn($q) => $q->where('task_assignees.employee_id', $employee->id),
                );

                $projectIds = Project::query()
                    ->where(function ($p) use ($employee) {
                        $p->where('owner_id', $employee->id)
                            ->orWhere('project_manager_id', $employee->id)
                            ->orWhereHas('members', fn($m) => $m->where('project_members.employee_id', $employee->id));
                    })
                    ->pluck('id');

                if ($projectIds->isNotEmpty()) {
                    $inner->orWhereIn('project_id', $projectIds);
                }
            }
        });
    }

    /** Non-query variant for policies. */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->can('tasks.view.all')) {
            return true;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        $employee = $user->employee()->first();

        if (
            $employee !== null
            && $this->assignees()->where('task_assignees.employee_id', $employee->id)->exists()
        ) {
            return true;
        }

        if ($this->project_id !== null) {
            return Project::query()->where('id', $this->project_id)->accessibleBy($user)->exists();
        }

        return false;
    }

    public function hasOpenDependencies(): bool
    {
        return $this->dependencies()
            ->whereNotIn('tasks.status', ['completed', 'cancelled'])
            ->exists();
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::get(
            fn() => $this->due_date !== null
                && ! in_array($this->status, ['completed', 'cancelled'], true)
                && $this->due_date->isPast()
        );
    }
}
