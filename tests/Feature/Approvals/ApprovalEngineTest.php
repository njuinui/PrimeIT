<?php

use App\Domain\Approvals\ApprovalEngine;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Notifications\Approvals\ApprovalDecidedNotification;
use App\Notifications\Approvals\ApprovalEscalatedNotification;
use App\Notifications\Approvals\ApprovalRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPermissions();

    $this->category = ExpenseCategory::create(['name' => 'Travel']);
    $this->engine   = app(ApprovalEngine::class);
});

function submitExpense($test, ExpenseCategory $category, $submitter): Expense
{
    Sanctum::actingAs($submitter);

    $id = $test->postJson('/api/v1/expenses', [
        'title' => 'Engine test', 'expense_category_id' => $category->uuid,
        'amount' => 150000, 'expense_date' => now()->toDateString(),
    ])->assertCreated()->json('data.id');

    $test->postJson("/api/v1/expenses/{$id}/submit")->assertOk();

    return Expense::where('uuid', $id)->first();
}

function makeWorkflow(array $steps, bool $active = true): ApprovalWorkflow
{
    $uid = strtoupper(uniqid());

    $workflow = ApprovalWorkflow::create([
        'name'        => 'WF '.$uid,
        'entity_type' => 'expense',
        'is_active'   => $active,
    ]);

    foreach (array_values($steps) as $index => $step) {
        ApprovalWorkflowStep::create(array_merge([
            'approval_workflow_id' => $workflow->id,
            'step_number'          => $index + 1,
            'name'                 => $step['name'],
            'approver_source'      => $step['approver_source'],
            'approver_role'        => $step['approver_role'] ?? null,
            'policy'               => $step['policy'] ?? 'any_one',
        ], $workflow ? [] : []));
    }

    // normalize: re-create cleanly to include escalation_days
    $workflow->steps()->delete();

    foreach (array_values($steps) as $index => $step) {
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_number'          => $index + 1,
            'name'                 => $step['name'],
            'approver_source'      => $step['approver_source'],
            'approver_role'        => $step['approver_role'] ?? null,
            'policy'               => $step['policy'] ?? 'any_one',
            'escalation_days'      => $step['escalation_days'] ?? null,
        ]);
    }

    return $workflow;
}

test('a two-step workflow advances and completes, driving the expense', function () {
    Notification::fake();

    $org = makeOrg();

    makeWorkflow([
        ['name' => 'Finance review', 'approver_source' => 'role', 'approver_role' => 'finance_manager'],
        ['name' => 'Line manager sign-off', 'approver_source' => 'requester_manager'],
    ]);

    $fin  = makeUser('finance_manager');
    $rita = userFor($org['report']);
    $mary = userFor($org['manager']); // Rita's manager

    $expense = submitExpense($this, $this->category, $rita);

    $request = ApprovalRequest::where('approvable_id', $expense->id)->first();
    expect($request->status)->toBe('pending');
    expect($request->steps()->count())->toBe(2);

    // Step 1 approvers notified; Mary not yet.
    Notification::assertSentTo($fin, ApprovalRequestedNotification::class);
    Notification::assertNotSentTo($mary, ApprovalRequestedNotification::class);

    // Finance approves step 1 via the approval center.
    Sanctum::actingAs($fin);

    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve', ['note' => 'OK'])
        ->assertOk()
        ->assertJsonPath('data.current_step', 2);

    // Step 2: Mary is now the approver and was notified.
    Notification::assertSentTo($mary, ApprovalRequestedNotification::class);

    // Rita cannot decide step 2 (she's the requester, not the manager).
    Sanctum::actingAs($rita);
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')->assertForbidden();

    Sanctum::actingAs($mary);
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $expense->refresh();
    $request->refresh();

    expect($expense->status)->toBe('approved');
    expect($expense->decided_by)->toBe($mary->id);
    expect($expense->history()->where('event', 'approved')->count())->toBe(1);

    Notification::assertSentTo($rita, ApprovalDecidedNotification::class);
});

test('a rejection at any step fast-fails the request and the expense', function () {
    Notification::fake();

    $org = makeOrg();

    makeWorkflow([
        ['name' => 'Finance review', 'approver_source' => 'role', 'approver_role' => 'finance_manager'],
        ['name' => 'Manager sign-off', 'approver_source' => 'requester_manager'],
    ]);

    $fin  = makeUser('finance_manager');
    $rita = userFor($org['report']);

    $expense = submitExpense($this, $this->category, $rita);

    $request = ApprovalRequest::where('approvable_id', $expense->id)->first();

    Sanctum::actingAs($fin);

    $this->postJson('/api/v1/approvals/'.$request->uuid.'/reject', ['note' => 'No receipt'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $expense->refresh();

    expect($expense->status)->toBe('rejected');
    expect($expense->decision_note)->toBe('No receipt');

    // Step 2 never fires.
    expect(userFor($org['manager'])->fresh()->notifications)->toHaveCount(0);

    Notification::assertSentTo($rita, ApprovalDecidedNotification::class);
});

test('policy=all requires every approver before advancing', function () {
    $org = makeOrg();

    makeWorkflow([
        ['name' => 'Both managers', 'approver_source' => 'role', 'approver_role' => 'manager', 'policy' => 'all'],
        ['name' => 'Finance', 'approver_source' => 'role', 'approver_role' => 'finance_manager'],
    ]);

    $rita   = userFor($org['report']);
    $mary   = userFor($org['manager']); // manager role
    $second = makeUser('manager');      // second manager

    $expense = submitExpense($this, $this->category, $rita);

    $request = ApprovalRequest::where('approvable_id', $expense->id)->first();

    Sanctum::actingAs($mary);
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.current_step', 1); // still waiting

    // Duplicate decision by Mary is rejected.
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')->assertStatus(422);

    Sanctum::actingAs($second);
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')
        ->assertOk()
        ->assertJsonPath('data.current_step', 2);

    Sanctum::actingAs(makeUser('finance_manager'));
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($expense->fresh()->status)->toBe('approved');
});

test('outsiders cannot decide; super admins can break glass', function () {
    $org = makeOrg();

    makeWorkflow([['name' => 'Finance', 'approver_source' => 'role', 'approver_role' => 'finance_manager']]);

    $expense = submitExpense($this, $this->category, userFor($org['report']));
    $request = ApprovalRequest::where('approvable_id', $expense->id)->first();

    // A plain employee (has approvals.decide? no) is blocked at the permission gate.
    Sanctum::actingAs(userFor($org['designer']));
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')->assertForbidden();

    // A manager (has approvals.decide) but NOT in the step's set is blocked by the engine.
    Sanctum::actingAs(userFor($org['manager']));
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')->assertForbidden();

    // The seeded super admin passes through.
    $super = makeUser('super_admin');
    Sanctum::actingAs($super);
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')
        ->assertOk()->assertJsonPath('data.status', 'approved');
});

test('the approval center queue, my requests and visibility rules', function () {
    $org = makeOrg();

    makeWorkflow([['name' => 'Finance', 'approver_source' => 'role', 'approver_role' => 'finance_manager']]);

    $fin  = makeUser('finance_manager');
    $rita = userFor($org['report']);

    $expense = submitExpense($this, $this->category, $rita);
    $request = ApprovalRequest::where('approvable_id', $expense->id)->first();

    // Rita sees her own request; finance sees it in the queue.
    Sanctum::actingAs($rita);
    $mine = $this->getJson('/api/v1/approvals?scope=requested')->assertOk();
    expect(count($mine->json('data')))->toBe(1);
    expect($mine->json('data.0.subject.title'))->toBe('Engine test');
    expect($mine->json('data.0.can_decide'))->toBeFalse();

    Sanctum::actingAs($fin);
    $queue = $this->getJson('/api/v1/approvals')->assertOk();
    expect(count($queue->json('data')))->toBe(1);
    expect($queue->json('data.0.can_decide'))->toBeTrue();

    // After deciding, it leaves the queue and appears in history.
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')->assertOk();

    expect(count($this->getJson('/api/v1/approvals')->assertOk()->json('data')))->toBe(0);

    // scope=all requires approvals.view.
    Sanctum::actingAs(userFor($org['report']));
    $this->getJson('/api/v1/approvals?scope=all')->assertForbidden();

    // But she can still open her own request's detail.
    $this->getJson('/api/v1/approvals/'.$request->uuid)->assertOk()
        ->assertJsonPath('data.status', 'approved');

    Sanctum::actingAs(userFor($org['designer']));
    $this->getJson('/api/v1/approvals/'.$request->uuid)->assertForbidden();
});

test('escalation adds the requester manager as approver and notifies them', function () {
    Notification::fake();

    $org = makeOrg();

    makeWorkflow([
        ['name' => 'Finance', 'approver_source' => 'role', 'approver_role' => 'finance_manager', 'escalation_days' => 2],
    ]);

    $rita = userFor($org['report']);
    $mary = userFor($org['manager']);

    $expense = submitExpense($this, $this->category, $rita);
    $request = ApprovalRequest::where('approvable_id', $expense->id)->first();

    // Age the step beyond its window.
    $request->steps()->update(['created_at' => now()->subDays(5)]);

    Notification::fake(); // discard the submit-time notifications

    $this->artisan('approvals:escalate')->assertExitCode(0);

    Notification::assertSentTo($mary, ApprovalEscalatedNotification::class);

    // Mary is now an approver on the escalated step.
    Sanctum::actingAs($mary);
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')
        ->assertOk()->assertJsonPath('data.status', 'approved');

    expect($expense->fresh()->status)->toBe('approved');
});

test('deleting an expense cancels its pending request; no workflow keeps the legacy path', function () {
    $org = makeOrg();

    makeWorkflow([['name' => 'Finance', 'approver_source' => 'role', 'approver_role' => 'finance_manager']]);

    $rita = userFor($org['report']);

    $expense = submitExpense($this, $this->category, $rita);
    $request = ApprovalRequest::where('approvable_id', $expense->id)->first();

    Sanctum::actingAs(makeUser('manager')); // tasks.delete holder? no — manager lacks finance delete; use admin
    Sanctum::actingAs(makeUser('admin'));
    $this->deleteJson('/api/v1/expenses/'.$expense->uuid)->assertOk();

    expect($request->fresh()->status)->toBe('cancelled');

    // ── Legacy fallback: no active workflow ────────────────────
    Notification::fake();

    ApprovalWorkflow::query()->update(['is_active' => false]);

    $fin = makeUser('finance_manager'); // must exist before submit

    $expense2 = submitExpense($this, $this->category, userFor($org['report']));

    Notification::assertSentTo($fin, App\Notifications\Finance\ExpenseSubmittedNotification::class);
    expect(ApprovalRequest::where('approvable_id', $expense2->id)->exists())->toBeFalse();

    // Direct decision still works without a workflow.
    Sanctum::actingAs($fin);
    $this->postJson('/api/v1/expenses/'.$expense2->uuid.'/approve')
        ->assertOk()->assertJsonPath('data.status', 'approved');
});

test('workflow management requires approvals.manage and activation is exclusive', function () {
    $org = makeOrg();
    Sanctum::actingAs(makeUser('admin'));

    $workflowId = $this->postJson('/api/v1/approval-workflows', [
        'name' => 'Custom single step', 'entity_type' => 'expense', 'is_active' => true,
        'steps' => [
            ['name' => 'Finance', 'approver_source' => 'role', 'approver_role' => 'finance_manager', 'policy' => 'any_one', 'escalation_days' => 5],
        ],
    ])->assertCreated()->json('data.id');

    // Activating it deactivated the seeded standard workflow.
    $active = ApprovalWorkflow::where('is_active', true)->where('entity_type', 'expense')->get();
    expect($active->count())->toBe(1);
    expect($active->first()->uuid)->toBe($workflowId);

    // Managers cannot manage workflows.
    Sanctum::actingAs(makeUser('manager'));
    $this->postJson('/api/v1/approval-workflows', [
        'name' => 'Sneaky', 'entity_type' => 'expense',
        'steps' => [['name' => 'X', 'approver_source' => 'role', 'approver_role' => 'manager', 'policy' => 'any_one']],
    ])->assertForbidden();

    Sanctum::actingAs(makeUser('admin'));

    // Validation: steps required, source must be known.
    $this->postJson('/api/v1/approval-workflows', [
        'name' => 'Bad', 'entity_type' => 'expense',
        'steps' => [['name' => 'X', 'approver_source' => 'wizard', 'policy' => 'any_one']],
    ])->assertStatus(422)->assertJsonValidationErrors('steps.0.approver_source');

    // Workflows with pending requests cannot be deleted.
    $expense = submitExpense($this, $this->category, userFor($org['report']));

    Sanctum::actingAs(makeUser('admin'));
    $this->deleteJson('/api/v1/approval-workflows/'.$workflowId)->assertStatus(409);

    // Clean up: decide the request, then delete works.
    Sanctum::actingAs(makeUser('finance_manager'));
    $request = ApprovalRequest::where('approvable_id', $expense->id)->first();
    $this->postJson('/api/v1/approvals/'.$request->uuid.'/approve')->assertOk();

    Sanctum::actingAs(makeUser('admin'));
    $this->deleteJson('/api/v1/approval-workflows/'.$workflowId)->assertOk();
});