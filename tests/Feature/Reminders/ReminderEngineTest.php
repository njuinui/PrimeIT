<?php

use App\Models\Reminder;
use App\Notifications\Tasks\TaskOverdueEscalatedNotification;
use App\Notifications\Tasks\TaskReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPermissions();
});

test('the scanner creates one reminder per recipient, task and threshold', function () {
    $org = makeOrg();

    $task = assignTask(makeTask(['due_date' => now()->addDays(7)->setTime(10, 0)]), $org['report']);
    $task->watchers()->attach(userFor($org['manager'])->id);

    Artisan::call('reminders:scan');

    expect(Reminder::where('type', 'task_due_7d')->count())->toBe(2);
    expect(Reminder::pluck('status')->unique()->values()->all())->toBe(['pending']);

    // Idempotent re-run — the unique index holds the line.
    Artisan::call('reminders:scan');
    expect(Reminder::count())->toBe(2);
});

test('every threshold, due-today, overdue and escalation fire exactly once', function () {
    $org = makeOrg();

    foreach ([7, 3, 2, 1] as $days) {
        assignTask(makeTask(['due_date' => now()->addDays($days)->setTime(15, 0)]), $org['report']);
    }

    assignTask(makeTask(['due_date' => now()->setTime(17, 0)]), $org['report']);             // due today
    assignTask(makeTask(['due_date' => now()->subDays(3)->setTime(12, 0)]), $org['report']); // overdue

    Artisan::call('reminders:scan');

    foreach ([7, 3, 2, 1] as $days) {
        expect(Reminder::where('type', "task_due_{$days}d")->count())->toBe(1);
    }

    expect(Reminder::where('type', 'task_due_today')->count())->toBe(1);
    expect(Reminder::where('type', 'task_overdue')->count())->toBe(1);

    // Rita reports to Mary → Mary gets the escalation for the overdue task.
    expect(Reminder::where('type', 'task_overdue_escalation')->count())->toBe(1);
    expect(Reminder::where('type', 'task_overdue_escalation')->first()->user_id)
        ->toBe(userFor($org['manager'])->id);
});

test('completed and cancelled tasks are never scanned', function () {
    $org = makeOrg();

    assignTask(makeTask(['due_date' => now()->addDays(3), 'status' => 'completed', 'completed_at' => now()]), $org['report']);
    assignTask(makeTask(['due_date' => now()->addDays(3), 'status' => 'cancelled']), $org['report']);

    Artisan::call('reminders:scan');

    expect(Reminder::count())->toBe(0);
});

test('managers who are themselves assignees get the regular reminder, not an escalation', function () {
    $org = makeOrg();

    // Mary (the manager) is an assignee; Rita reports to Mary.
    assignTask(makeTask(['due_date' => now()->subDays(2)->setTime(12, 0)]), $org['manager'], $org['report']);

    Artisan::call('reminders:scan');

    expect(Reminder::where('type', 'task_overdue')->count())->toBe(2);
    expect(Reminder::where('type', 'task_overdue_escalation')->count())->toBe(0);
});

test('send delivers reminders through the preference-aware pipeline exactly once', function () {
    Notification::fake();

    $org = makeOrg();
    $rita = userFor($org['report']);

    assignTask(makeTask(['title' => 'Ship the invoice module', 'due_date' => now()->addDays(2)->setTime(12, 0)]), $org['report']);

    Artisan::call('reminders:scan');
    Artisan::call('reminders:send');

    // Default task_reminder preference = in-app + email.
    Notification::assertSentTo(
        $rita,
        TaskReminderNotification::class,
        fn (TaskReminderNotification $notification, array $channels) => $channels === ['database', 'mail'],
    );

    $reminder = Reminder::first();
    expect($reminder->status)->toBe('sent');
    expect($reminder->sent_at)->not->toBeNull();

    Artisan::call('reminders:send');
    Notification::assertSentToTimes($rita, TaskReminderNotification::class, 1);
});

test('escalation notifications name the assignee and the overdue task', function () {
    $org = makeOrg();
    $mary = userFor($org['manager']);

    assignTask(makeTask([
        'title'    => 'Fix the payment webhook',
        'due_date' => now()->subDays(3)->setTime(12, 0),
    ]), $org['report']);

    Artisan::call('reminders:scan');
    Artisan::call('reminders:send');

    $notifications = $mary->fresh()->notifications;

    expect($notifications)->toHaveCount(1);

    $data = $notifications->first()->data;
    expect($data['category'])->toBe('task_reminder');
    expect($data['title'])->toBe('Overdue task on your team');
    expect($data['body'])->toContain('Fix the payment webhook');
    expect($data['body'])->toContain('Rita Report');
    expect($data['body'])->toContain('3 days overdue');
});

test('completing a task cancels its pending reminders', function () {
    $org = makeOrg();

    $task = assignTask(makeTask(['due_date' => now()->addDays(1)->setTime(12, 0)]), $org['report']);

    Artisan::call('reminders:scan');
    expect(Reminder::where('status', 'pending')->count())->toBe(1);

    Sanctum::actingAs(userFor($org['manager']));
    $this->patchJson('/api/v1/tasks/'.$task->uuid.'/status', ['status' => 'completed'])->assertOk();

    expect(Reminder::first()->status)->toBe('cancelled');

    Notification::fake();
    Artisan::call('reminders:send');
    Notification::assertNothingSent();
});

test('moving a due date cancels stale reminders so the next scan recreates correct ones', function () {
    $org = makeOrg();

    $task = assignTask(makeTask(['due_date' => now()->addDays(2)->setTime(12, 0)]), $org['report']);

    Artisan::call('reminders:scan');
    expect(Reminder::where('type', 'task_due_2d')->where('status', 'pending')->count())->toBe(1);

    Sanctum::actingAs(userFor($org['manager']));

    $this->putJson('/api/v1/tasks/'.$task->uuid, [
        'title'    => $task->title,
        'status'   => $task->status,
        'priority' => $task->priority,
        'due_date' => now()->addDays(10)->toDateString(),
    ])->assertOk();

    expect(Reminder::where('status', 'pending')->count())->toBe(0);

    Artisan::call('reminders:scan');
    expect(Reminder::where('status', 'pending')->count())->toBe(0); // 10 days out — no threshold applies
});

test('deleting a task cancels its pending reminders', function () {
    $org = makeOrg();

    $task = assignTask(makeTask(['due_date' => now()->addDays(3)->setTime(12, 0)]), $org['report']);

    Artisan::call('reminders:scan');
    expect(Reminder::count())->toBe(1);

    Sanctum::actingAs(makeUser('manager'));
    $this->deleteJson('/api/v1/tasks/'.$task->uuid)->assertOk();

    expect(Reminder::first()->status)->toBe('cancelled');
});

test('muted reminder preferences suppress delivery entirely', function () {
    Notification::fake();

    $org = makeOrg();
    $rita = userFor($org['report']);

    assignTask(makeTask(['due_date' => now()->addDays(1)->setTime(12, 0)]), $org['report']);

    Sanctum::actingAs($rita);
    $this->putJson('/api/v1/notification-preferences', [
        'preferences' => [['category' => 'task_reminder', 'in_app' => false, 'email' => false]],
    ])->assertOk();

    Artisan::call('reminders:scan');
    Artisan::call('reminders:send');

    Notification::assertNotSentTo($rita, TaskReminderNotification::class);
    expect(Reminder::first()->status)->toBe('sent'); // consumed, not retried
});

test('the task index supports date-range filters for the calendar', function () {
    $org = makeOrg();

    assignTask(makeTask(['due_date' => now()->addDays(2)->setTime(12, 0)]), $org['report']);
    assignTask(makeTask(['due_date' => now()->addDays(20)->setTime(12, 0)]), $org['report']);

    Sanctum::actingAs(makeUser('manager'));

    $from = now()->startOfDay()->toDateString();
    $to = now()->addDays(7)->toDateString();

    $response = $this->getJson("/api/v1/tasks?due_from={$from}&due_to={$to}")->assertOk();

    expect(collect($response->json('data')))->toHaveCount(1);
});