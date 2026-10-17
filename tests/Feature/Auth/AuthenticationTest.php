<?php

use App\Models\LoginLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('auth');
});

test('a user can sign in and receive a token', function () {
    $user = User::factory()->create(['email' => 'john.doe@primeit.com', 'password' => 'Password@123']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email'       => 'john.doe@primeit.com',
        'password'    => 'Password@123',
        'device_name' => 'Pest Runner',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']],
        ]);

    expect($response->json('data.token'))->toBeString();
    expect($user->fresh()->last_login_at)->not->toBeNull();
    expect(LoginLog::where('event', 'login')->where('successful', true)->exists())->toBeTrue();
});

test('invalid credentials are rejected generically and audited', function () {
    User::factory()->create(['email' => 'john.doe@primeit.com', 'password' => 'Password@123']);

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'john.doe@primeit.com',
        'password' => 'WrongPassword@1',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect(LoginLog::where('event', 'login_failed')->where('successful', false)->exists())->toBeTrue();
});

test('inactive accounts cannot sign in', function () {
    User::factory()->create([
        'email'    => 'suspended@primeit.com',
        'password' => 'Password@123',
        'status'   => 'suspended',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'suspended@primeit.com',
        'password' => 'Password@123',
    ])->assertStatus(403)->assertJsonPath('success', false);
});

test('sign-in attempts are rate limited', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => 'victim@primeit.com', 'password' => 'nope']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'victim@primeit.com', 'password' => 'nope'])
        ->assertStatus(429);
});

test('an authenticated user can fetch their profile', function () {
    User::factory()->create(['email' => 'me@primeit.com', 'password' => 'Password@123']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'me@primeit.com', 'password' => 'Password@123',
    ])->json('data.token');

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'me@primeit.com');
});

test('the profile endpoint requires authentication', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('an employee can update their own profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_number' => 'EMP-0001',
        'first_name' => 'Photo',
        'last_name' => 'Owner',
        'employment_type' => 'full_time',
        'status' => 'active',
    ]);
    Sanctum::actingAs($user);

    $response = $this->post('/api/v1/auth/profile/photo', [
        'photo' => UploadedFile::fake()->createWithContent(
            'profile.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/ZAAAAABJRU5ErkJggg=='),
        ),
    ], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('message', 'Profile photo updated.')
        ->assertJsonPath('data.employee.id', $employee->uuid);

    $storedPath = $employee->fresh()->avatar;
    expect($storedPath)->not->toBeNull();
    Storage::disk('public')->assertExists($storedPath);
    expect($response->json('data.employee.avatar_url'))->toBe(Storage::disk('public')->url($storedPath));
});

test('logout revokes the current token', function () {
    User::factory()->create(['email' => 'out@primeit.com', 'password' => 'Password@123']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'out@primeit.com', 'password' => 'Password@123',
    ])->json('data.token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    expect(LoginLog::where('event', 'logout')->exists())->toBeTrue();
});