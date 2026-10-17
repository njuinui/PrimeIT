<?php

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('auth');
});

test('forgot password sends a reset link email', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'john.doe@primeit.com', 'password' => 'Password@123']);

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'john.doe@primeit.com'])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    expect(DB::table('password_reset_tokens')->where('email', 'john.doe@primeit.com')->exists())->toBeTrue();
});

test('forgot password never reveals unknown accounts', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ghost@primeit.com'])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertNothingSent();
});

test('a password can be reset with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset@primeit.com',
        'password' => 'Password@123',
    ]);

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'reset@primeit.com',
    ])->assertOk();

    $token = null;

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        }
    );

    expect($token)
        ->not->toBeNull()
        ->not->toBeEmpty();

    $this->postJson('/api/v1/auth/password/reset', [
        'token'                 => $token,
        'email'                 => 'reset@primeit.com',
        'password'              => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'reset@primeit.com',
        'password' => 'Password@123',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'reset@primeit.com',
        'password' => 'NewPassword@123',
    ])->assertOk();
});

test('reset with an invalid token fails', function () {
    User::factory()->create(['email' => 'reset2@primeit.com', 'password' => 'Password@123']);

    $this->postJson('/api/v1/auth/password/reset', [
        'token'                 => 'invalid-token-value',
        'email'                 => 'reset2@primeit.com',
        'password'              => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertStatus(422)->assertJsonPath('success', false);
});

test('a user can change their password and other sessions are revoked', function () {
    User::factory()->create(['email' => 'change@primeit.com', 'password' => 'Password@123']);

    $tokenA = $this->postJson('/api/v1/auth/login', [
        'email' => 'change@primeit.com',
        'password' => 'Password@123',
        'device_name' => 'Laptop',
    ])->json('data.token');

    $tokenB = $this->postJson('/api/v1/auth/login', [
        'email' => 'change@primeit.com',
        'password' => 'Password@123',
        'device_name' => 'Phone',
    ])->json('data.token');

    // Wrong current password
    $this->withToken($tokenA)->postJson('/api/v1/auth/password/change', [
        'current_password'      => 'WrongPassword@1',
        'password'              => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');

    // Correct change
    $this->withToken($tokenA)->postJson('/api/v1/auth/password/change', [
        'current_password'      => 'Password@123',
        'password'              => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertOk();

    $this->withToken($tokenB)->getJson('/api/v1/auth/me')->assertUnauthorized(); // other session revoked
    $this->withToken($tokenA)->getJson('/api/v1/auth/me')->assertOk();           // current session survives

    $this->postJson('/api/v1/auth/login', [
        'email' => 'change@primeit.com',
        'password' => 'NewPassword@123',
    ])->assertOk();
});
