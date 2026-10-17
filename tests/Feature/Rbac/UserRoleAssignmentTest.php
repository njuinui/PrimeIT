<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
});

function rbacUser(string ...$roles): User
{
    $user = User::factory()->create();

    if ($roles !== []) {
        $user->syncRoles($roles);
    }

    return $user->fresh();
}

test('the users index requires users.view', function () {
    Sanctum::actingAs(rbacUser('employee'));
    $this->getJson('/api/v1/users')->assertForbidden();

    Sanctum::actingAs(rbacUser('hr'));
    $this->getJson('/api/v1/users')->assertOk();
});

test('the users index supports search and role filters', function () {
    rbacUser('manager', 'employee');
    $other = rbacUser('employee');

    Sanctum::actingAs(rbacUser('hr'));

    $byRole = $this->getJson('/api/v1/users?role=manager')->assertOk();
    expect(count($byRole->json('data')))->toBe(1);

    $bySearch = $this->getJson('/api/v1/users?search='.$other->email)->assertOk();
    expect(count($bySearch->json('data')))->toBe(1);
    expect($bySearch->json('data.0.email'))->toBe($other->email);
});

test('users.update holders can sync another user roles', function () {
    Sanctum::actingAs(rbacUser('hr'));

    $target = rbacUser('employee');

    $this->putJson("/api/v1/users/{$target->uuid}/roles", [
        'roles' => ['employee', 'manager'],
    ])->assertOk()->assertJsonPath('message', 'Roles updated.');

    expect($target->fresh()->getRoleNames()->sort()->values()->all())->toBe(['employee', 'manager']);
});

test('nobody can change their own roles', function () {
    $hr = rbacUser('hr');
    Sanctum::actingAs($hr);

    $this->putJson("/api/v1/users/{$hr->uuid}/roles", [
        'roles' => ['super_admin'],
    ])->assertForbidden();

    expect($hr->fresh()->hasRole('super_admin'))->toBeFalse();
});

test('only super admins can grant the super admin role', function () {
    Sanctum::actingAs(rbacUser('admin'));

    $target = rbacUser('employee');

    $this->putJson("/api/v1/users/{$target->uuid}/roles", [
        'roles' => ['employee', 'super_admin'],
    ])->assertForbidden();

    Sanctum::actingAs(rbacUser('super_admin'));

    $this->putJson("/api/v1/users/{$target->uuid}/roles", [
        'roles' => ['employee', 'super_admin'],
    ])->assertOk();

    expect($target->fresh()->hasRole('super_admin'))->toBeTrue();
});

test('the last super admin cannot be demoted', function () {
    Sanctum::actingAs(rbacUser('admin')); // has users.update, is not a super admin

    $onlySuper = rbacUser('super_admin');

    $this->putJson("/api/v1/users/{$onlySuper->uuid}/roles", [
        'roles' => ['employee'],
    ])->assertForbidden();

    expect($onlySuper->fresh()->hasRole('super_admin'))->toBeTrue();
});

test('assigning an unknown role is rejected', function () {
    Sanctum::actingAs(rbacUser('hr'));

    $target = rbacUser('employee');

    $this->putJson("/api/v1/users/{$target->uuid}/roles", [
        'roles' => ['wizard'],
    ])->assertStatus(422)->assertJsonValidationErrors('roles.0');
});