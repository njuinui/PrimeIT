<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    protected $fillable = [
        'approval_workflow_id', 'step_number', 'name', 'approver_source',
        'approver_role', 'approver_user_ids', 'policy', 'escalation_days',
    ];

    protected function casts(): array
    {
        return [
            'approver_user_ids' => 'array',
            'step_number'       => 'integer',
            'escalation_days'   => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }
}