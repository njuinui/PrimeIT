<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const STATUSES   = ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled', 'archived'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const STATUS_PLANNING  = 'planning';
    public const STATUS_ACTIVE    = 'in_progress';
    public const STATUS_ON_HOLD   = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'project_number', 'name', 'slug', 'description', 'status', 'priority',
        'owner_id', 'project_manager_id', 'department_id', 'start_date', 'due_date',
        'completed_at', 'budget', 'currency', 'progress', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date'   => 'date',
            'due_date'     => 'date',
            'completed_at' => 'datetime',
            'budget'       => 'decimal:2',
            'progress'     => 'integer',
        ];
    }

    // ── Relations ─────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'project_manager_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class)->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->latest('id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot(['role', 'added_by'])
            ->withTimestamps();
    }

    // ── Scoping ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED, 'archived']);
    }

    /**
     * Projects the given user may see: everything with projects.view.all,
     * otherwise only owned / managed / member projects.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->can('projects.view.all')) {
            return $query;
        }

        $employee = $user->employee()->first();

        if ($employee === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($employee) {
            $inner->where('owner_id', $employee->id)
                ->orWhere('project_manager_id', $employee->id)
                ->orWhereHas('members', fn ($member) => $member->where('project_members.employee_id', $employee->id));
        });
    }

    /**
     * Pure participation check (owner, PM or member) — no permissions involved.
     */
    public function isParticipant(User $user): bool
    {
        $employee = $user->employee()->first();

        if ($employee === null) {
            return false;
        }

        if ($this->owner_id === $employee->id || $this->project_manager_id === $employee->id) {
            return true;
        }

        return $this->members()->where('project_members.employee_id', $employee->id)->exists();
    }
}