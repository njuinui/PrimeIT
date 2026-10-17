<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MeetingParticipant extends Pivot
{
    public $timestamps = true;

    protected $table = 'meeting_participants';

    protected $fillable = [
        'meeting_id', 'employee_id', 'attendance', 'response', 'responded_at', 'added_by',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}