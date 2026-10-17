<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Collection;

class Employee extends Model
{
    use HasFactory;
    use HasPublicUuid;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'personal_email',
        'department_id',
        'team_id',
        'manager_id',
        'job_title',
        'employment_type',
        'hire_date',
        'status',
        'avatar',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    /**
     * User account associated with this employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return ! $this->isActive();
    }

    /**
     * Department this employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Team this employee belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Employee's manager.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /**
     * Employees directly reporting to this employee.
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /**
     * Tasks assigned to this employee.
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->using(TaskAssignee::class)
            ->withPivot([
                'assigned_by',
                'assigned_at',
                'accepted_at',
                'declined_at',
                'decline_reason',
                'completed_at',
            ])
            ->withTimestamps();
    }

    /**
     * Employee's full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn() => trim("{$this->first_name} {$this->last_name}")
        );
    }


    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Walks up the management chain using explicit queries (safe under
     * preventLazyLoading), capped to avoid infinite loops on bad data.
     */
    public function managerChain(int $maxDepth = 10): Collection
    {
        $chain = new Collection();
        $managerId = $this->manager_id;
        $depth = 0;

        while ($managerId !== null && $depth < $maxDepth) {
            $manager = static::query()->find($managerId);

            if ($manager === null) {
                break;
            }

            $chain->push($manager);
            $managerId = $manager->manager_id;
            $depth++;
        }

        return $chain;
    }

    /**
     * True when $user manages this employee directly or indirectly.
     */
    public function isManagedBy(User $user): bool
    {
        return $this->managerChain()
            ->contains(fn(Employee $manager) => (int) $manager->user_id === (int) $user->id);
    }
}
