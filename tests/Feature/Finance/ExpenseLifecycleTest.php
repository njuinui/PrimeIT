<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Notifications\Finance\ExpenseDecidedNotification;
use App\Notifications\Finance\ExpenseSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPermissions();

    $this->category = ExpenseCategory::create(['name' => 'Travel']);
});

function expensePayload(ExpenseCategory $category, array $overrides = []): array
{
    return array_merge([
        'title'               => 'Flight to Douala',
        'expense_category_id' => $category->uuid,
        'amount'              => 250000,
        'expense_date'        => now()->toDateString(),
        'description'         => 'Client workshop',
    ], $overrides);
}

test('an employee can save an expense draft with a receipt', function () {
    Storage::fake('local');

    $org = makeOrg();
    Sanctum::actingAs(userFor($org['report']));

    $response = $this->postJson('/api/v1/expenses', expensePayload($this->category, [
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
    ]))->assertCreated()->assertJsonPath('data.status', 'draft');

    $expense = Expense::where('expense_number', $response->json('data.expense_number'))->first();

    expect($expense->user_id)->toBe(userFor($org['report'])->id);
    expect($expense->department_id)->toBe($org['report']->department_id);
    expect($expense->currency)->toBe('XAF');
    expect($expense->receipt_path)->not->toBeNull();

    Storage::disk('local')->assertExists($expense->receipt_path);
});

test('receipt uploads are validated by type and size', function () {
    $org = makeOrg();
    Sanctum::actingAs(userFor($org['report']));

    $this->postJson('/api/v1/expenses', expensePayload($this->category, [
        'receipt' => UploadedFile::fake()->create('malware.exe', 100),
    ]))->assertStatus(422)->assertJsonValidationErrors('receipt');
});

test('submitting notifies the current workflow step approvers and records history', function () {
    Notification::fake();

    $org = makeOrg();
    $approver = makeUser('finance_manager'); // exists BEFORE the submit

    Sanctum::actingAs(userFor($org['report']));

    $id = $this->postJson('/api/v1/expenses', expensePayload($this->category))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/expenses/{$id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    Notification::assertSentTo($approver, App\Notifications\Approvals\ApprovalRequestedNotification::class);
    Notification::assertNotSentTo($approver, App\Notifications\Finance\ExpenseSubmittedNotification::class);

    $expense = Expense::where('uuid', $id)->first();

    expect($expense->history()->count())->toBe(2);
    expect($expense->history()->latest('id')->first()->event)->toBe('submitted');
});

test('approving stamps the decision and notifies the submitter', function () {
    Notification::fake();

    $org = makeOrg();
    $rita = userFor($org['report']);
    $approver = makeUser('finance_manager');

    Sanctum::actingAs($rita);
    $id = $this->postJson('/api/v1/expenses', expensePayload($this->category))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/expenses/{$id}/submit")->assertOk();

    Sanctum::actingAs($approver);

    $this->postJson("/api/v1/expenses/{$id}/approve", ['note' => 'Receipt verified'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.decision_note', 'Receipt verified');

    $expense = Expense::where('uuid', $id)->first();

    expect($expense->decided_by)->toBe($approver->id);
    expect($expense->decided_at)->not->toBeNull();

    Notification::assertSentTo($rita, App\Notifications\Approvals\ApprovalDecidedNotification::class);
});

test('rejection flows and rejected expenses can be resubmitted', function () {
    $org = makeOrg();

    Sanctum::actingAs(userFor($org['report']));
    $id = $this->postJson('/api/v1/expenses', expensePayload($this->category))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/expenses/{$id}/submit")->assertOk();

    Sanctum::actingAs(makeUser('finance_manager'));
    $this->postJson("/api/v1/expenses/{$id}/reject", ['note' => 'No receipt'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    // Decisions are final — cannot re-reject.
    $this->postJson("/api/v1/expenses/{$id}/reject")->assertStatus(409);

    // Owner can resubmit after fixing the issue.
    Sanctum::actingAs(userFor($org['report']));
    $this->postJson("/api/v1/expenses/{$id}/submit")
        ->assertOk()->assertJsonPath('data.status', 'submitted');
});

test('only submitted expenses can be decided and only approvers may decide', function () {
    $org = makeOrg();

    Sanctum::actingAs(userFor($org['report']));
    $id = $this->postJson('/api/v1/expenses', expensePayload($this->category))->assertCreated()->json('data.id');

    // Not submitted yet.
    Sanctum::actingAs(makeUser('finance_manager'));
    $this->postJson("/api/v1/expenses/{$id}/approve")->assertStatus(409);

    // A plain employee cannot decide even after submission.
    Sanctum::actingAs(userFor($org['report']));
    $this->postJson("/api/v1/expenses/{$id}/submit")->assertOk();
    $this->postJson("/api/v1/expenses/{$id}/approve")->assertForbidden();

    // And cannot update once submitted.
    $this->putJson("/api/v1/expenses/{$id}", expensePayload($this->category, ['amount' => 999]))
        ->assertForbidden();
});

test('finance scoping: employees see only their own expenses; approvers see all', function () {
    $org = makeOrg();

    Sanctum::actingAs(userFor($org['report']));
    $this->postJson('/api/v1/expenses', expensePayload($this->category))->assertCreated();

    Sanctum::actingAs(userFor($org['designer']));
    $this->postJson('/api/v1/expenses', expensePayload($this->category))->assertCreated();

    Sanctum::actingAs(userFor($org['report']));
    expect(count($this->getJson('/api/v1/expenses')->assertOk()->json('data')))->toBe(1);

    Sanctum::actingAs(makeUser('finance_manager'));
    expect(count($this->getJson('/api/v1/expenses')->assertOk()->json('data')))->toBe(2);

    // A stranger cannot open someone else's expense.
    Sanctum::actingAs(userFor($org['designer']));
    $foreign = Expense::where('user_id', userFor($org['report'])->id)->first();
    $this->getJson('/api/v1/expenses/'.$foreign->uuid)->assertForbidden();
});

test('receipts download only for allowed viewers', function () {
    Storage::fake('local');

    $org = makeOrg();

    Sanctum::actingAs(userFor($org['report']));

    $id = $this->postJson('/api/v1/expenses', expensePayload($this->category, [
        'receipt' => UploadedFile::fake()->create('receipt.png', 80, 'image/png'),
    ]))->assertCreated()->json('data.id');

    Sanctum::actingAs(userFor($org['report']));
    $this->getJson("/api/v1/expenses/{$id}/receipt")->assertOk();

    Sanctum::actingAs(userFor($org['designer']));
    $this->getJson("/api/v1/expenses/{$id}/receipt")->assertForbidden();

    Sanctum::actingAs(makeUser('finance_manager'));
    $this->getJson("/api/v1/expenses/{$id}/receipt")->assertOk();
});