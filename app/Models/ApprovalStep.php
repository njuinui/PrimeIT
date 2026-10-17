<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalStep extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'approval_request_id', 'step_number', 'name', 'approver_source',
        'approver_role', 'approver_user_ids', 'escalated_user_ids',
        'policy', 'escalation_days', 'status', 'decided_at', 'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'approver_user_ids' => 'array',
            'escalated_user_ids' => 'array',
            'decided_at'  => 'datetime',
            'escalated_at' => 'datetime',
            'step_number' => 'integer',
            'escalation_days' => 'integer',
        ];
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class)->latest('id');
    }
}