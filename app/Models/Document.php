<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const STATUSES = ['active', 'locked', 'archived'];

    protected $fillable = [
        'folder_id', 'title', 'description', 'file_name', 'file_path', 'mime_type',
        'size_bytes', 'current_version', 'status', 'tags',
        'project_id', 'department_id', 'owner_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tags'          => 'array',
            'size_bytes'    => 'integer',
            'current_version' => 'integer',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(DocumentGrant::class);
    }

    /**
     * Documents visible to this user: everything for manage holders;
     * otherwise granted ∨ owned ∨ my department ∨ my projects ∨ created by me.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->can('documents.manage')) {
            return $query;
        }

        $employee = $user->employee()->first();

        return $query->where(function (Builder $inner) use ($user, $employee) {
            $inner->where('created_by', $user->id)
                ->orWhereHas('grants', fn ($g) => $g->where('document_grants.user_id', $user->id));

            if ($employee !== null) {
                $inner->orWhere('owner_id', $employee->id);

                if ($employee->department_id !== null) {
                    $inner->orWhere('department_id', $employee->department_id);
                }
            }

            $projectIds = Project::query()->accessibleBy($user)->pluck('id');

            if ($projectIds->isNotEmpty()) {
                $inner->orWhereIn('project_id', $projectIds);
            }
        });
    }
}