<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'paid'];

    protected $fillable = [
        'user_id', 'employee_id', 'expense_category_id', 'project_id', 'department_id',
        'expense_number', 'title', 'description', 'amount', 'currency', 'expense_date', 'status',
        'decided_by', 'decided_at', 'decision_note', 'paid_at', 'receipt_path', 'receipt_name',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'expense_date' => 'date',
            'decided_at'  => 'datetime',
            'paid_at'     => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ExpenseHistory::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    // ── Helpers ───────────────────────────────────────────────

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max(0.0, (float) $this->amount - $this->paidAmount());
    }

    public function recordHistory(?User $user, string $event, ?string $from, ?string $to, ?string $note = null): ExpenseHistory
    {
        return ExpenseHistory::create([
            'expense_id'  => $this->id,
            'user_id'     => $user?->id,
            'event'       => $event,
            'from_status' => $from,
            'to_status'   => $to,
            'note'        => $note !== null ? mb_substr($note, 0, 500) : null,
        ]);
    }
}