<?php

use App\Events\Realtime\RealtimeNotificationCreated;
use App\Events\Realtime\TaskBoardUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPermissions();
});

// ── Board events ──────────────────────────────────────────────

test('task creation broadcasts a minimal board event', function () {
    Event::fake([TaskBoardUpdated::class, RealtimeNotificationCreated::class]);

    $org = makeOrg();

    Sanctum::actingAs(userFor($org['manager']));

    $response = $this->postJson('/api/v1/tasks', [
        'title'     => 'Realtime task',
        'status'    => 'todo',
        'priority'  => 'medium',
        'assignees' => [$org['report']->uuid],
    ])->assertCreated();

    $taskId = $response->json('data.id');

    Event::assertDispatched(TaskBoardUpdated::class, fn (TaskBoardUpdated $event) =>
        $event->event === 'task.created'
        && $event->taskId === $taskId
        && $event->status === 'todo'
        && $event->broadcastOn()[0]->name === 'private-workspace.tasks');

    // The payload must never carry the title.
    Event::assertDispatched(TaskBoardUpdated::class, fn (TaskBoardUpdated $event) =>
        ! in_array('Realtime task', $event->broadcastWith(), true));

    // The assignee's notification push fired as well.
    Event::assertDispatched(RealtimeNotificationCreated::class, fn (RealtimeNotificationCreated $event) =>
        $event->userUuid === $org['report']->user->uuid
        && $event->data['category'] === 'task_assigned'
        && $event->broadcastOn()[0]->name === 'private-App.Models.User.'.$event->userUuid);
});

test('status changes broadcast task.status_changed; deletes broadcast task.deleted', function () {
    Event::fake([TaskBoardUpdated::class]);

    $org = makeOrg();
    Sanctum::actingAs(userFor($org['manager']));

    $taskId = $this->postJson('/api/v1/tasks', [
        'title' => 'Board event', 'status' => 'todo', 'priority' => 'low',
    ])->assertCreated()->json('data.id');

    Event::assertDispatchedTimes(TaskBoardUpdated::class, 1); // created

    $this->patchJson("/api/v1/tasks/{$taskId}/status", ['status' => 'in_progress'])->assertOk();

    Event::assertDispatched(TaskBoardUpdated::class, fn (TaskBoardUpdated $event) =>
        $event->event === 'task.status_changed' && $event->status === 'in_progress');

    $this->putJson("/api/v1/tasks/{$taskId}", [
        'title' => 'Board event', 'status' => 'in_progress', 'priority' => 'low',
    ])->assertOk();

    Event::assertDispatched(TaskBoardUpdated::class, fn (TaskBoardUpdated $event) =>
        $event->event === 'task.updated');

    $this->deleteJson("/api/v1/tasks/{$taskId}")->assertOk();

    Event::assertDispatched(TaskBoardUpdated::class, fn (TaskBoardUpdated $event) =>
        $event->event === 'task.deleted' && $event->status === null);
});

// ── Channel authorization ─────────────────────────────────────

function authChannel(string $channel): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/broadcasting/auth', ['channel_name' => $channel]);
}

test('broadcasting auth requires a token', function () {
    $this->postJson('/api/v1/broadcasting/auth', [
        'channel_name' => 'private-workspace.tasks',
    ])->assertUnauthorized();
});

test('a user may authorize only their own notification channel', function () {
    $org = makeOrg();
    $rita = userFor($org['report']);

    Sanctum::actingAs($rita);

    authChannel('private-App.Models.User.'.$rita->uuid)->assertOk();
    authChannel('private-App.Models.User.'.userFor($org['designer'])->uuid)->assertForbidden();
});

test('the workspace tasks channel requires tasks.view', function () {
    $org = makeOrg();

    Sanctum::actingAs(User::factory()->create()); // no role at all
    authChannel('private-workspace.tasks')->assertForbidden();

    Sanctum::actingAs(userFor($org['report']));   // employee
    authChannel('private-workspace.tasks')->assertOk();
});

test('phase 9 reminder notifications are pushed to the user channel too', function () {
    Event::fake([RealtimeNotificationCreated::class]);

    $org = makeOrg();

    assignTask(makeTask(['due_date' => now()->addDays(2)->setTime(12, 0)]), $org['report']);

    Artisan::call('reminders:scan');
    Artisan::call('reminders:send');

    Event::assertDispatched(RealtimeNotificationCreated::class, fn (RealtimeNotificationCreated $event) =>
        $event->userUuid === $org['report']->user->uuid
        && $event->data['category'] === 'task_reminder'
        && str_contains($event->data['title'], 'due in 2 days'));
});