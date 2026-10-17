<?php

use App\Models\LoginLog;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Support\SignedFrontendUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('auth');
});

test('a verification email can be requested', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'verify@primeit.com', 'password' => 'Password@123',
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'verify@primeit.com', 'password' => 'Password@123',
    ])->json('data.token');

    $this->withToken($token)->postJson('/api/v1/auth/email/resend-verification')
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('a valid signed link verifies the email address', function () {
    $user = User::factory()->unverified()->create(['email' => 'link@primeit.com']);

    $url = SignedFrontendUrl::create('/verify-email', [
        'id'   => $user->id,
        'hash' => sha1('link@primeit.com'),
    ], 60);

    parse_str((string) parse_url($url, PHP_URL_QUERY), $payload);

    $this->postJson('/api/v1/auth/email/verify', $payload)->assertOk();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
    expect(LoginLog::where('event', 'email_verified')->exists())->toBeTrue();

    // Idempotent — replaying the same link still succeeds.
    $this->postJson('/api/v1/auth/email/verify', $payload)->assertOk();
});

test('a tampered signature is rejected', function () {
    $user = User::factory()->unverified()->create(['email' => 'tamper@primeit.com']);

    $url = SignedFrontendUrl::create('/verify-email', [
        'id' => $user->id, 'hash' => sha1('tamper@primeit.com'),
    ], 60);

    parse_str((string) parse_url($url, PHP_URL_QUERY), $payload);
    $payload['signature'] = str_repeat('a', 64);

    $this->postJson('/api/v1/auth/email/verify', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('an expired verification link is rejected', function () {
    $user = User::factory()->unverified()->create(['email' => 'expired@primeit.com']);

    $url = SignedFrontendUrl::create('/verify-email', [
        'id' => $user->id, 'hash' => sha1('expired@primeit.com'),
    ], -10);

    parse_str((string) parse_url($url, PHP_URL_QUERY), $payload);

    $this->postJson('/api/v1/auth/email/verify', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});