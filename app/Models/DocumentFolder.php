<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentFolder extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $fillable = ['name', 'parent_id', 'department_id', 'created_by'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Manage holders see every folder; everyone else sees global folders
     * plus their own department's.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->can('documents.manage')) {
            return $query;
        }

        $employee = $user->employee()->first();

        if ($employee === null || $employee->department_id === null) {
            return $query->whereNull('department_id');
        }

        return $query->where(function ($inner) use ($employee) {
            $inner->whereNull('department_id')
                ->orWhere('department_id', $employee->department_id);
        });
    }
}