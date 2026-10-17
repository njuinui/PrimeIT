<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('auth');
});

function totpCodeFor(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

function wrongCodeFor(string $secret): string
{
    return str_pad((string) ((((int) totpCodeFor($secret)) + 1) % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Runs enable → confirm over real HTTP. Returns [token, secret, recovery codes].
 */
function setUpTwoFactor($test, string $email): array
{
    $token = $test->postJson('/api/v1/auth/login', [
        'email' => $email, 'password' => 'Password@123',
    ])->json('data.token');

    $enable = $test->withToken($token)
        ->postJson('/api/v1/auth/two-factor/enable')
        ->assertOk()
        ->json('data');

    $confirm = $test->withToken($token)
        ->postJson('/api/v1/auth/two-factor/confirm', ['code' => totpCodeFor($enable['secret'])])
        ->assertOk()
        ->json('data');

    return [$token, $enable['secret'], $confirm['codes']];
}

test('two-factor can be enabled and confirmed', function () {
    $user = User::factory()->create(['email' => '2fa@primeit.com', 'password' => 'Password@123']);

    [$token, $secret, $codes] = setUpTwoFactor($this, '2fa@primeit.com');

    expect($secret)->toHaveLength(32);
    expect($codes)->toHaveCount(10);
    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

test('an invalid confirmation code is rejected', function () {
    $user = User::factory()->create(['email' => '2fa2@primeit.com', 'password' => 'Password@123']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa2@primeit.com', 'password' => 'Password@123',
    ])->json('data.token');

    $enable = $this->withToken($token)->postJson('/api/v1/auth/two-factor/enable')->assertOk()->json('data');

    $this->withToken($token)
        ->postJson('/api/v1/auth/two-factor/confirm', ['code' => wrongCodeFor($enable['secret'])])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('login requires the two-factor challenge when enabled', function () {
    User::factory()->create(['email' => '2fa3@primeit.com', 'password' => 'Password@123']);
    setUpTwoFactor($this, '2fa3@primeit.com');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa3@primeit.com', 'password' => 'Password@123',
    ]);

    $response->assertOk()->assertJsonPath('data.two_factor_required', true);

    expect($response->json('data.token'))->toBeNull();
    expect($response->json('data.challenge_token'))->toBeString();
});

test('the challenge completes with a valid code and is single-use', function () {
    User::factory()->create(['email' => '2fa4@primeit.com', 'password' => 'Password@123']);
    [, $secret] = setUpTwoFactor($this, '2fa4@primeit.com');

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa4@primeit.com', 'password' => 'Password@123',
    ])->json('data.challenge_token');

    $response = $this->postJson('/api/v1/auth/two-factor/challenge/verify', [
        'challenge_token' => $challenge,
        'code'            => totpCodeFor($secret),
        'device_name'     => 'Pest Runner',
    ])->assertOk();

    $token = $response->json('data.token');
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

    // The challenge token can never be replayed.
    $this->postJson('/api/v1/auth/two-factor/challenge/verify', [
        'challenge_token' => $challenge,
        'code'            => totpCodeFor($secret),
    ])->assertStatus(422);
});

test('wrong challenge codes are rejected without burning the challenge', function () {
    User::factory()->create(['email' => '2fa5@primeit.com', 'password' => 'Password@123']);
    [, $secret] = setUpTwoFactor($this, '2fa5@primeit.com');

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa5@primeit.com', 'password' => 'Password@123',
    ])->json('data.challenge_token');

    $this->postJson('/api/v1/auth/two-factor/challenge/verify', [
        'challenge_token' => $challenge,
        'code'            => wrongCodeFor($secret),
    ])->assertStatus(422)->assertJsonValidationErrors('code');

    // Still valid after a typo.
    $this->postJson('/api/v1/auth/two-factor/challenge/verify', [
        'challenge_token' => $challenge,
        'code'            => totpCodeFor($secret),
    ])->assertOk();
});

test('a recovery code signs in exactly once', function () {
    User::factory()->create(['email' => '2fa6@primeit.com', 'password' => 'Password@123']);
    [, , $codes] = setUpTwoFactor($this, '2fa6@primeit.com');

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa6@primeit.com', 'password' => 'Password@123',
    ])->json('data.challenge_token');

    $this->postJson('/api/v1/auth/two-factor/challenge/recovery', [
        'challenge_token' => $challenge,
        'recovery_code'   => $codes[0],
    ])->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);

    // The code is spent — a fresh challenge rejects it.
    $challenge2 = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa6@primeit.com', 'password' => 'Password@123',
    ])->json('data.challenge_token');

    $this->postJson('/api/v1/auth/two-factor/challenge/recovery', [
        'challenge_token' => $challenge2,
        'recovery_code'   => $codes[0],
    ])->assertStatus(422);
});

test('two-factor can be disabled with the account password', function () {
    $user = User::factory()->create(['email' => '2fa7@primeit.com', 'password' => 'Password@123']);
    [$token] = setUpTwoFactor($this, '2fa7@primeit.com');

    $this->withToken($token)->postJson('/api/v1/auth/two-factor/disable', [
        'current_password' => 'WrongPassword@1',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');

    $this->withToken($token)->postJson('/api/v1/auth/two-factor/disable', [
        'current_password' => 'Password@123',
    ])->assertOk();

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa7@primeit.com', 'password' => 'Password@123',
    ])->assertOk();

    expect($response->json('data.two_factor_required'))->toBeNull();
    expect($response->json('data.token'))->toBeString();
});

test('recovery codes can be regenerated and old ones die', function () {
    User::factory()->create(['email' => '2fa8@primeit.com', 'password' => 'Password@123']);
    [$token, , $oldCodes] = setUpTwoFactor($this, '2fa8@primeit.com');

    $new = $this->withToken($token)
        ->postJson('/api/v1/auth/two-factor/recovery-codes', ['current_password' => 'Password@123'])
        ->assertOk()
        ->json('data.codes');

    expect($new)->toHaveCount(10);
    expect($new[0])->not->toBe($oldCodes[0]);

    // Old code is dead…
    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa8@primeit.com', 'password' => 'Password@123',
    ])->json('data.challenge_token');

    $this->postJson('/api/v1/auth/two-factor/challenge/recovery', [
        'challenge_token' => $challenge,
        'recovery_code'   => $oldCodes[0],
    ])->assertStatus(422);

    // …and the new one works.
    $challenge2 = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa8@primeit.com', 'password' => 'Password@123',
    ])->json('data.challenge_token');

    $this->postJson('/api/v1/auth/two-factor/challenge/recovery', [
        'challenge_token' => $challenge2,
        'recovery_code'   => $new[0],
    ])->assertOk();
});

test('sessions can be listed and other devices revoked', function () {
    User::factory()->create(['email' => 'sessions@primeit.com', 'password' => 'Password@123']);

    $tokenA = $this->postJson('/api/v1/auth/login', [
        'email' => 'sessions@primeit.com', 'password' => 'Password@123', 'device_name' => 'Work Laptop',
    ])->json('data.token');

    $tokenB = $this->postJson('/api/v1/auth/login', [
        'email' => 'sessions@primeit.com', 'password' => 'Password@123', 'device_name' => 'Phone',
    ])->json('data.token');

    $sessions = $this->withToken($tokenA)
        ->getJson('/api/v1/auth/sessions')
        ->assertOk()
        ->json('data');

    expect($sessions)->toHaveCount(2);
    expect(collect($sessions)->where('name', 'Work Laptop')->first()['current'])->toBeTrue();
    expect(collect($sessions)->where('name', 'Phone')->first()['current'])->toBeFalse();

    $this->withToken($tokenA)->postJson('/api/v1/auth/sessions/revoke-others')->assertOk();

    $this->withToken($tokenB)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($tokenA)->getJson('/api/v1/auth/me')->assertOk();
});

test('login history is available to the account owner', function () {
    User::factory()->create(['email' => 'history@primeit.com', 'password' => 'Password@123']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'history@primeit.com', 'password' => 'Password@123',
    ])->json('data.token');

    $this->withToken($token)->getJson('/api/v1/auth/login-history')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.event', 'login');
});