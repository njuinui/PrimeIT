<?php

namespace App\Domain\Approvals;

use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\User;
use App\Notifications\Approvals\ApprovalDecidedNotification;
use App\Notifications\Approvals\ApprovalEscalatedNotification;
use App\Notifications\Approvals\ApprovalRequestedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovalEngine
{
    /**
     * Starts a workflow for an approvable entity, if one is configured.
     * Returns null when no active workflow exists (caller falls back to
     * its own legacy behavior).
     */
    public function startFor(Model $approvable, User $requester): ?ApprovalRequest
    {
        $workflow = ApprovalWorkflow::query()
            ->where('entity_type', $approvable->getMorphClass())
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($workflow === null || $workflow->steps()->count() === 0) {
            return null;
        }

        $request = ApprovalRequest::create([
            'workflow_id'      => $workflow->id,
            'approvable_type'  => $approvable->getMorphClass(),
            'approvable_id'    => $approvable->getKey(),
            'requested_by'     => $requester->id,
            'status'           => 'pending',
            'current_step'     => 1,
        ]);

        foreach ($workflow->steps()->orderBy('step_number')->get() as $definition) {
            ApprovalStep::create([
                'approval_request_id' => $request->id,
                'step_number'         => $definition->step_number,
                'name'                => $definition->name,
                'approver_source'     => $definition->approver_source,
                'approver_role'       => $definition->approver_role,
                'approver_user_ids'   => $definition->approver_user_ids,
                'policy'              => $definition->policy,
                'escalation_days'     => $definition->escalation_days,
                'status'              => 'pending',
            ]);
        }

        $this->autoAdvanceEmptySteps($request);

        return $request->fresh();
    }

    /**
     * Records a decision on the current step and advances the workflow.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when the user is not an approver for the current step
     * @throws ValidationException                                             on duplicate decisions
     */
    public function recordDecision(ApprovalRequest $request, User $user, bool $approve, ?string $note): ApprovalRequest
    {
        $step = $request->currentStep();

        if ($step === null) {
            throw ValidationException::withMessages([
                'request' => ['This approval request has already been completed.'],
            ]);
        }

        if (! $this->isAllowedApprover($step, $user)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'You are not an approver for the current step of this request.'
            );
        }

        $alreadyDecided = ApprovalDecision::query()
            ->where('approval_step_id', $step->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyDecided) {
            throw ValidationException::withMessages([
                'decision' => ['You have already recorded a decision on this step.'],
            ]);
        }

        ApprovalDecision::create([
            'approval_request_id' => $request->id,
            'approval_step_id'    => $step->id,
            'user_id'             => $user->id,
            'decision'            => $approve ? 'approved' : 'rejected',
            'note'                => $note !== null ? mb_substr($note, 0, 1000) : null,
        ]);

        // ── Rejection: fast-fail the whole request ─────────────
        if (! $approve) {
            $step->forceFill(['status' => 'rejected', 'decided_at' => now()])->save();
            $request->forceFill(['status' => 'rejected', 'completed_at' => now()])->save();

            $this->applyOutcome($request->fresh(), $user, false, $note);
            $this->notifyRequester($request->fresh(), $user, false);

            return $request->fresh();
        }

        // ── Approval: check the step policy ────────────────────
        $approvers = $this->approverUserIds($step);
        $needed    = $step->policy === 'any_one' ? 1 : max(1, count($approvers));

        $approvedCount = ApprovalDecision::query()
            ->where('approval_step_id', $step->id)
            ->where('decision', 'approved')
            ->count();

        if ($approvedCount < $needed) {
            return $request->fresh(); // waiting on co-approvers (policy: all)
        }

        $step->forceFill(['status' => 'approved', 'decided_at' => now()])->save();
        $request = $request->fresh();

        $next = $request->currentStep();

        if ($next !== null) {
            $request->forceFill(['current_step' => $next->step_number])->save();
            $this->notifyStepApprovers($request->fresh(), $next);

            return $request->fresh();
        }

        // ── All steps satisfied ─────────────────────────────────
        $request->forceFill([
            'status'       => 'approved',
            'completed_at' => now(),
            'current_step' => (int) $request->steps()->max('step_number'),
        ])->save();

        $this->applyOutcome($request->fresh(), $user, true, $note);
        $this->notifyRequester($request->fresh(), $user, true);

        return $request->fresh();
    }

    public function canDecide(ApprovalRequest $request, User $user): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        $step = $request->currentStep();

        if ($step === null) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! in_array($user->id, $this->approverUserIds($step), true)) {
            return false;
        }

        return ! ApprovalDecision::query()
            ->where('approval_step_id', $step->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function isAllowedApprover(ApprovalStep $step, User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true; // break-glass
        }

        return in_array($user->id, $this->approverUserIds($step), true);
    }

    /**
     * Current computed approver ids for a runtime step — role holders,
     * the requester's manager, specific users, plus anyone escalated in.
     *
     * @return array<int, int>
     */
    public function approverUserIds(ApprovalStep $step): array
    {
        $request = $step->approvalRequest()->first();
        $requesterId = $request?->requested_by;

        $ids = match ($step->approver_source) {
            'role' => $step->approver_role !== null
                ? User::role($step->approver_role)->where('status', 'active')->pluck('id')->all()
                : [],
            'requester_manager' => [$this->requesterManagerUserId($requesterId)],
            'specific_users'    => $step->approver_user_ids ?? [],
            default             => [],
        };

        return collect($ids)
            ->merge($step->escalated_user_ids ?? [])
            ->filter(fn ($id) => $id !== null && (int) $id > 0 && (int) $id !== $requesterId)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Cancels every pending request attached to a model (e.g. the expense
     * was deleted). Returns the number cancelled.
     */
    public function cancelFor(Model $approvable): int
    {
        return ApprovalRequest::query()
            ->where('approvable_type', $approvable->getMorphClass())
            ->where('approvable_id', $approvable->getKey())
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'completed_at' => now()]);
    }

    /**
     * Hourly escalation sweep: pending steps past their escalation window
     * gain the requester's manager as an approver and notify them.
     */
    public function escalateStale(): int
    {
        $escalated = 0;

        ApprovalStep::query()
            ->where('status', 'pending')
            ->whereNotNull('escalation_days')
            ->whereNull('escalated_at')
            ->get()
            ->each(function (ApprovalStep $step) use (&$escalated): void {
                $deadline = $step->created_at?->copy()->addDays($step->escalation_days);

                if ($deadline === null || $deadline->isFuture()) {
                    return;
                }

                $request = $step->approvalRequest()->first();
                $managerUser = $request !== null
                    ? $this->requesterManagerUser($request->requested_by)
                    : null;

                $extra = $step->escalated_user_ids ?? [];

                if ($managerUser !== null
                    && ! in_array($managerUser->id, $this->approverUserIds($step), true)) {
                    $extra[] = $managerUser->id;
                }

                $step->forceFill([
                    'escalated_user_ids' => array_values(array_unique(array_map('intval', $extra))),
                    'escalated_at'       => now(),
                ])->save();

                if ($managerUser !== null) {
                    $managerUser->notify(
                        new ApprovalEscalatedNotification($request->fresh(), $step->fresh()),
                    );
                }

                $escalated++;
            });

        return $escalated;
    }

    // ── Internals ─────────────────────────────────────────────

    private function autoAdvanceEmptySteps(ApprovalRequest $request): void
    {
        while (($step = $request->currentStep()) !== null) {
            $approvers = $this->approverUserIds($step);

            if ($approvers !== []) {
                $this->notifyStepApprovers($request, $step);

                return;
            }

            if ($step->approver_source === 'requester_manager' && $this->requesterManagerUserId($request->requested_by) === null) {
                $step->forceFill(['status' => 'approved', 'decided_at' => now()])->save();
                $request = $request->fresh();
                continue;
            }

            // Leave empty role/specific-user workflow steps pending so a real approver
            // can decide later; auto-passing here creates false approvals and breaks the
            // access checks the tests rely on.
            return;
        }

        $request->forceFill(['status' => 'approved', 'completed_at' => now()])->save();
        $this->applyOutcome($request->fresh(), User::find($request->requested_by), true, null);
    }

    private function notifyStepApprovers(ApprovalRequest $request, ApprovalStep $step): void
    {
        User::query()
            ->whereIn('id', $this->approverUserIds($step))
            ->get()
            ->each(fn (User $approver) => $approver->notify(
                new ApprovalRequestedNotification($request, $step),
            ));
    }

    private function notifyRequester(ApprovalRequest $request, User $actor, bool $approved): void
    {
        $requester = $request->requester()->first();

        if ($requester === null || $requester->id === $actor->id) {
            return;
        }

        $requester->notify(new ApprovalDecidedNotification($request, $actor, $approved));
    }

    /**
     * Drives the underlying entity to its outcome. Expenses today;
     * documents and meetings plug in here in later phases.
     */
    private function applyOutcome(ApprovalRequest $request, User $actor, bool $approved, ?string $note): void
    {
        $approvable = $request->approvable()->first();

        if ($approvable instanceof Expense) {
            if ($approvable->status !== 'submitted') {
                return; // stale — the expense moved on without the workflow
            }

            $approvable->forceFill([
                'status'       => $approved ? 'approved' : 'rejected',
                'decided_by'   => $actor->id,
                'decided_at'   => now(),
                'decision_note' => $note,
            ])->save();

            $approvable->recordHistory(
                $actor,
                $approved ? 'approved' : 'rejected',
                'submitted',
                $approved ? 'approved' : 'rejected',
                $note,
            );
        }
    }

    private function requesterManagerUserId(?int $requesterId): ?int
    {
        return $this->requesterManagerUser($requesterId)?->id;
    }

    private function requesterManagerUser(?int $requesterId): ?User
    {
        if ($requesterId === null) {
            return null;
        }

        $employee = Employee::query()->where('user_id', $requesterId)->first();

        if ($employee === null || $employee->manager_id === null) {
            return null;
        }

        $manager = Employee::query()->find($employee->manager_id);

        if ($manager === null || $manager->user_id === null) {
            return null;
        }

        return User::query()->find($manager->user_id);
    }
}