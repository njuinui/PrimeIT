<?php

use App\Models\Meeting;
use App\Models\Reminder;
use App\Notifications\Meetings\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPermissions();
});

function makeMeetingAt($org, $startsAt, array $attributes = []): Meeting
{
    Sanctum::actingAs(userFor($org['manager']));

    $id = test()->postJson('/api/v1/meetings', array_merge([
        'title'      => 'Reminder target',
        'starts_at'  => $startsAt,
        'ends_at'    => $startsAt->copy()->addHour(),
        'organized_by' => $org['manager']->uuid,
        'participants' => [
            ['employee_id' => $org['report']->uuid, 'attendance' => 'required'],
        ],
    ], $attributes))->assertCreated()->json('data.id');

    Sanctum::actingAs(makeUser('admin')); // neutral actor for scans

    return Meeting::where('uuid', $id)->first();
}

test('the scanner queues 24h and 15m reminders for participants and the organizer', function () {
    $org = makeOrg();

    $soon = makeMeetingAt($org, now()->addMinutes(10));
    $later = makeMeetingAt($org, now()->addHours(20));
    $far = makeMeetingAt($org, now()->addDays(5));

    Artisan::call('reminders:scan');

    // 15m: Rita + Mary (organizer) = 2; 24h: same two = 2.
    expect(Reminder::where('type', 'meeting_upcoming_15m')->count())->toBe(2);
    expect(Reminder::where('type', 'meeting_upcoming_24h')->count())->toBe(2);
    expect(Reminder::where('remindable_id', $far->id)->count())->toBe(0);

    // Idempotent re-run.
    Artisan::call('reminders:scan');
    expect(Reminder::where('remindable_type', 'meeting')->count())->toBe(4);

    expect(Reminder::where('type', 'meeting_upcoming_15m')->pluck('user_id'))
        ->toContain(userFor($org['report'])->id, userFor($org['manager'])->id);
});

test('cancelled and completed meetings are never scanned', function () {
    $org = makeOrg();

    $cancelled = makeMeetingAt($org, now()->addMinutes(10), ['status' => 'cancelled']);
    $completed = makeMeetingAt($org, now()->addMinutes(10), ['status' => 'completed']);

    Artisan::call('reminders:scan');

    expect(Reminder::whereIn('remindable_id', [$cancelled->id, $completed->id])->count())->toBe(0);
});

test('send delivers meeting reminders exactly once, honoring preferences', function () {
    Notification::fake();

    $org = makeOrg();
    $rita = userFor($org['report']);

    makeMeetingAt($org, now()->addMinutes(10));

    Artisan::call('reminders:scan');
    Artisan::call('reminders:send');

    Notification::assertSentTo($rita, MeetingReminderNotification::class);

    $reminder = Reminder::where('user_id', $rita->id)->first();
    expect($reminder->status)->toBe('sent');

    Artisan::call('reminders:send');
    Notification::assertSentToTimes($rita, MeetingReminderNotification::class, 1);

    // A muted category suppresses delivery for the next recipient.
    Notification::fake();

    $mary = userFor($org['manager']);

    Sanctum::actingAs($mary);
    test()->putJson('/api/v1/notification-preferences', [
        'preferences' => [['category' => 'meeting_reminder', 'in_app' => false, 'email' => false]],
    ])->assertOk();

    makeMeetingAt($org, now()->addMinutes(10));

    Artisan::call('reminders:scan');
    Artisan::call('reminders:send');

    Notification::assertNotSentTo($mary, MeetingReminderNotification::class);
});

test('reminders for meetings cancelled after scheduling are skipped by the sender', function () {
    Notification::fake();

    $org = makeOrg();

    $meeting = makeMeetingAt($org, now()->addMinutes(10));

    Artisan::call('reminders:scan');
    expect(Reminder::where('status', 'pending')->count())->toBe(2);

    // Simulate an out-of-band status flip without the cancellation hook.
    $meeting->forceFill(['status' => 'cancelled'])->save();

    Artisan::call('reminders:send');

    Notification::assertNothingSent();
    expect(Reminder::where('status', 'cancelled')->count())->toBe(2);
});