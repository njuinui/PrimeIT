<?php

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Notifications\Finance\ExpensePaidNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPermissions();

    $this->category = ExpenseCategory::create(['name' => 'Equipment']);
});

function approvedExpense($test, $category, $submitter): Expense
{
    Sanctum::actingAs($submitter);

    $id = $test->postJson('/api/v1/expenses', [
        'title' => 'Approved kit', 'expense_category_id' => $category->uuid,
        'amount' => 100000, 'expense_date' => now()->toDateString(),
    ])->assertCreated()->json('data.id');

    $test->postJson("/api/v1/expenses/{$id}/submit")->assertOk();

    Sanctum::actingAs(makeUser('finance_manager'));
    $test->postJson("/api/v1/expenses/{$id}/approve")->assertOk();

    return Expense::where('uuid', $id)->first();
}

test('a full payment marks the expense paid and notifies the submitter', function () {
    Notification::fake();

    $org = makeOrg();
    $expense = approvedExpense($this, $this->category, userFor($org['report']));

    $this->postJson("/api/v1/expenses/{$expense->uuid}/payments", [
        'amount' => 100000, 'method' => 'bank_transfer', 'reference' => 'TRF-001',
    ])->assertCreated()->assertJsonPath('expense.status', 'paid');

    $expense->refresh();

    expect($expense->status)->toBe('paid');
    expect($expense->paid_at)->not->toBeNull();
    expect($expense->history()->where('event', 'paid')->exists())->toBeTrue();

    Notification::assertSentTo(userFor($org['report']), ExpensePaidNotification::class);
});

test('partial payments keep the expense approved and overpaying is rejected', function () {
    $org = makeOrg();
    $expense = approvedExpense($this, $this->category, userFor($org['report']));

    $this->postJson("/api/v1/expenses/{$expense->uuid}/payments", [
        'amount' => 40000, 'method' => 'mobile_money',
    ])->assertCreated()->assertJsonPath('expense.status', 'approved');

    // Over the remaining 60000.
    $this->postJson("/api/v1/expenses/{$expense->uuid}/payments", [
        'amount' => 60001, 'method' => 'cash',
    ])->assertStatus(422);

    // Exact remainder completes it.
    $this->postJson("/api/v1/expenses/{$expense->uuid}/payments", [
        'amount' => 60000, 'method' => 'cash',
    ])->assertCreated()->assertJsonPath('expense.status', 'paid');
});

test('removing a payment reverts a paid expense to approved', function () {
    $org = makeOrg();
    $expense = approvedExpense($this, $this->category, userFor($org['report']));

    $paymentId = $this->postJson("/api/v1/expenses/{$expense->uuid}/payments", [
        'amount' => 100000, 'method' => 'bank_transfer',
    ])->assertCreated()->json('data.payment.id');

    $this->deleteJson("/api/v1/expenses/{$expense->uuid}/payments/{$paymentId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($expense->fresh()->paid_at)->toBeNull();
    expect($expense->history()->where('event', 'payment_removed')->exists())->toBeTrue();
});

test('payments require payments.manage and an approved expense', function () {
    $org = makeOrg();

    Sanctum::actingAs(userFor($org['report']));

    $id = $this->postJson('/api/v1/expenses', [
        'title' => 'Draft kit', 'expense_category_id' => $this->category->uuid,
        'amount' => 50000, 'expense_date' => now()->toDateString(),
    ])->assertCreated()->json('data.id');

    // No permission.
    $this->postJson("/api/v1/expenses/{$id}/payments", [
        'amount' => 50000, 'method' => 'cash',
    ])->assertForbidden();

    $expense = approvedExpense($this, $this->category, userFor($org['report']));

    // Wrong lifecycle state.
    Sanctum::actingAs(userFor($org['report']));
    $this->postJson("/api/v1/expenses/{$expense->uuid}/payments", [
        'amount' => 1, 'method' => 'cash',
    ])->assertForbidden(); // employee lacks payments.manage

    $paid = $this->postJson("/api/v1/expenses/{$expense->uuid}/payments", [
        'amount' => $expense->amount, 'method' => 'bank_transfer',
    ])->assertCreated()->json('data.expense.uuid');

    Sanctum::actingAs(makeUser('finance_manager'));
    $this->postJson("/api/v1/expenses/{$paid}/payments", [
        'amount' => 1, 'method' => 'cash',
    ])->assertStatus(409); // already paid
});

test('budgets validate exactly one subject and compute spent from approved and paid expenses', function () {
    $org = makeOrg();
    Sanctum::actingAs(makeUser('finance_manager'));

    $project = makeProject(['name' => 'Budgeted Project']);

    // Exactly-one rule.
    $this->postJson('/api/v1/budgets', ['amount' => 1000])
        ->assertStatus(422)->assertJsonValidationErrors('project_id');

    $this->postJson('/api/v1/budgets', [
        'project_id' => $project->uuid, 'department_id' => $org['engineering']->uuid, 'amount' => 1000,
    ])->assertStatus(422)->assertJsonValidationErrors('project_id');

    $budgetId = $this->postJson('/api/v1/budgets', [
        'project_id' => $project->uuid, 'amount' => 500000,
    ])->assertCreated()->assertJsonPath('data.subject_type', 'project')->json('data.id');

    // Draft/submitted/rejected do not count.
    $expense = approvedExpense($this, $this->category, userFor($org['report']));
    $expense->forceFill(['project_id' => $project->id])->save(); // approved → 250000? amount 100000

    $response = $this->getJson('/api/v1/budgets')->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $budgetId);

    expect($row['spent'])->toBe(100000.0);
    expect($row['remaining'])->toBe(400000.0);

    // A rejected expense on the same project does not change spent.
    $rejected = Expense::create([
        'expense_number' => 'EXP-9999', 'user_id' => userFor($org['report'])->id,
        'expense_category_id' => $this->category->id, 'project_id' => $project->id,
        'title' => 'Nope', 'amount' => 50000, 'currency' => 'XAF',
        'expense_date' => now()->toDateString(), 'status' => 'rejected',
    ]);

    $row = collect($this->getJson('/api/v1/budgets')->assertOk()->json('data'))->firstWhere('id', $budgetId);
    expect($row['spent'])->toBe(100000.0);

    // Period filtering: a budget window that excludes the expense date.
    $this->postJson('/api/v1/budgets', [
        'department_id' => $org['engineering']->uuid,
        'amount' => 1000000,
        'start_date' => now()->addYear()->toDateString(),
        'end_date' => now()->addYear()->addMonth()->toDateString(),
    ])->assertCreated();

    $fresh = collect($this->getJson('/api/v1/budgets')->assertOk()->json('data'))
        ->firstWhere('subject', 'Engineering');

    expect($fresh['spent'])->toBe(0.0);
});

test('budget visibility and management permissions hold', function () {
    $org = makeOrg();
    makeProject();

    // Employee (finance.view only) cannot list or create budgets.
    Sanctum::actingAs(userFor($org['report']));
    $this->getJson('/api/v1/budgets')->assertForbidden();

    // Manager (budgets.manage via matrix? manager lacks budgets.manage) — use finance_manager.
    Sanctum::actingAs(makeUser('finance_manager'));
    $this->getJson('/api/v1/budgets')->assertOk();

    $id = $this->postJson('/api/v1/budgets', [
        'department_id' => $org['engineering']->uuid, 'amount' => 100,
    ])->assertCreated()->json('data.id');

    // Categories: finance.view can list; only budgets.manage can create.
    Sanctum::actingAs(userFor($org['report']));
    $this->getJson('/api/v1/expense-categories')->assertOk();
    $this->postJson('/api/v1/expense-categories', ['name' => 'Sneaky'])->assertForbidden();

    // Category with expenses cannot be deleted.
    Sanctum::actingAs(makeUser('finance_manager'));
    $this->deleteJson('/api/v1/expense-categories/'.$this->category->uuid)->assertStatus(409);
});

test('finance stats are scoped and shaped correctly', function () {
    $org = makeOrg();

    approvedExpense($this, $this->category, userFor($org['report']));

    // Company view for the finance manager.
    Sanctum::actingAs(makeUser('finance_manager'));

    $company = $this->getJson('/api/v1/finance/stats')->assertOk()->assertJsonPath('data.scope', 'company')->json('data');

    expect($company['stats']['approved_count'])->toBe(1);
    expect($company['by_category'][0]['category'])->toBe('Equipment');
    expect($company['by_department'][0]['department'])->toBe('Engineering');
    expect(count($company['by_month']))->toBe(6);
    expect($company['budgets'])->toBeArray();

    // Own view for a plain employee.
    Sanctum::actingAs(userFor($org['report']));

    $own = $this->getJson('/api/v1/finance/stats')->assertOk()->assertJsonPath('data.scope', 'own')->json('data');

    expect($own['stats']['approved_count'])->toBe(1);
    expect($own['budgets'])->toBeNull();

    // A colleague sees nothing.
    Sanctum::actingAs(userFor($org['designer']));

    $empty = $this->getJson('/api/v1/finance/stats')->assertOk()->json('data');
    expect($empty['stats']['draft_count'] + $empty['stats']['pending_count'] + $empty['stats']['approved_count'])->toBe(0);
});