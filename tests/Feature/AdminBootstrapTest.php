<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => seedPermissions());

test('production refuses to create an admin with a default password', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['primeit.admin.password' => null]);

    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', config('primeit.admin.email'))->exists())->toBeFalse();
});

test('production creates the admin with the configured password', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['primeit.admin.email' => 'boss@example.com', 'primeit.admin.password' => 'Str0ngPassw0rd!']);

    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'boss@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->hasRole('super_admin'))->toBeTrue()
        ->and(password_verify('Str0ngPassw0rd!', $admin->password))->toBeTrue();
});

test('re-running the seeder never resets an existing admin password', function () {
    config(['primeit.admin.email' => 'boss@example.com', 'primeit.admin.password' => 'FirstPassw0rd!']);
    $this->seed(AdminUserSeeder::class);

    config(['primeit.admin.password' => 'SecondPassw0rd!']);
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'boss@example.com')->first();

    expect(password_verify('FirstPassw0rd!', $admin->password))->toBeTrue();
});

test('the primeit:admin command creates a super admin', function () {
    $this->artisan('primeit:admin', ['--email' => 'ops@example.com', '--name' => 'Ops Lead', '--password' => 'Str0ngPassw0rd!', '--no-interaction' => true])
        ->assertSuccessful();

    expect(User::where('email', 'ops@example.com')->first()?->hasRole('super_admin'))->toBeTrue();
});

test('the primeit:admin command rejects a weak password', function () {
    $this->artisan('primeit:admin', ['--email' => 'ops@example.com', '--name' => 'Ops Lead', '--password' => 'abc', '--no-interaction' => true])
        ->assertFailed();

    expect(User::where('email', 'ops@example.com')->exists())->toBeFalse();
});
