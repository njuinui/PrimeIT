<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectRisk extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const IMPACTS     = ['low', 'medium', 'high', 'critical'];
    public const LIKELIHOODS = ['low', 'medium', 'high'];
    public const STATUSES    = ['open', 'mitigating', 'closed'];

    protected $fillable = [
        'project_id', 'title', 'description', 'impact', 'likelihood',
        'status', 'mitigation', 'owner_id', 'review_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}