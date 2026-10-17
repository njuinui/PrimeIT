<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\Catalog;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
});

function rbacSignIn(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return Sanctum::actingAs($user->fresh());
}

test('the permission catalog and role matrix are fully seeded', function () {
    expect(Permission::count())->toBe(count(Catalog::all()));
    expect(Role::count())->toBe(6);
    expect(Role::where('is_system', true)->count())->toBe(6);

    $manager = Role::findByName('manager');
    expect($manager->permissions->pluck('name'))->toContain('tasks.assign');
    expect($manager->permissions->pluck('name'))->not->toContain('roles.manage');

    $employee = Role::findByName('employee');
    expect($employee->permissions->pluck('name'))->not->toContain('projects.view.all');
});

test('super admins bypass gates even with an empty permission set', function () {
    $super = User::factory()->create();
    $super->assignRole('super_admin');

    // Strip every permission from the role — the bypass must still hold.
    Role::findByName('super_admin')->syncPermissions([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Sanctum::actingAs($super->fresh());

    $this->getJson('/api/v1/roles')->assertOk();           // roles.view removed, bypass holds
    $this->getJson('/api/v1/dashboard/stats')->assertOk(); // dashboard.view removed, bypass holds
});

test('roles index requires the roles.view permission', function () {
    rbacSignIn('employee');
    $this->getJson('/api/v1/roles')->assertForbidden();

    rbacSignIn('admin');
    $response = $this->getJson('/api/v1/roles')->assertOk();

    expect(count($response->json('data')))->toBe(6);
    expect($response->json('data.0.permissions'))->toBeArray();
    expect($response->json('data.0.users_count'))->toBeInt();
});

test('the permission catalog endpoint returns grouped permissions', function () {
    rbacSignIn('admin');

    $response = $this->getJson('/api/v1/permissions')->assertOk();

    expect(count($response->json('data')))->toBe(count(Catalog::groups()));
    expect($response->json('data.0.group'))->toBe('Dashboard');
});

test('an administrator can create a custom role with permissions', function () {
    rbacSignIn('admin');

    $this->postJson('/api/v1/roles', [
        'name'        => 'project_viewer',
        'description' => 'Read-only access to projects',
        'permissions' => ['dashboard.view', 'projects.view'],
    ])->assertCreated()->assertJsonPath('data.name', 'project_viewer');

    $role = Role::findByName('project_viewer');
    expect($role->is_system)->toBeFalse();
    expect($role->permissions)->toHaveCount(2);
});

test('role creation validates name format, uniqueness and permissions', function () {
    rbacSignIn('admin');

    $this->postJson('/api/v1/roles', ['name' => 'Bad Name!', 'permissions' => []])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/v1/roles', ['name' => 'manager', 'permissions' => []])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/v1/roles', ['name' => 'custom_role', 'permissions' => ['not.a.permission']])
        ->assertStatus(422)->assertJsonValidationErrors('permissions.0');
});

test('users without roles.manage cannot create roles', function () {
    rbacSignIn('manager'); // manager lacks roles.manage

    $this->postJson('/api/v1/roles', [
        'name' => 'sneaky_role', 'permissions' => ['dashboard.view'],
    ])->assertForbidden();

    expect(Role::where('name', 'sneaky_role')->exists())->toBeFalse();
});

test('custom roles can be renamed and their permissions synced', function () {
    rbacSignIn('admin');

    $role = Role::create(['name' => 'contractor', 'guard_name' => 'web', 'is_system' => false]);
    $role->syncPermissions(['dashboard.view']);

    $this->putJson("/api/v1/roles/{$role->uuid}", [
        'name'        => 'external_contractor',
        'description' => 'External partners',
    ])->assertOk()->assertJsonPath('data.name', 'external_contractor');

    $this->putJson("/api/v1/roles/{$role->uuid}/permissions", [
        'permissions' => ['dashboard.view', 'tasks.view', 'tasks.comment'],
    ])->assertOk();

    expect($role->fresh()->permissions)->toHaveCount(3);
});

test('system roles cannot be renamed or deleted', function () {
    rbacSignIn('admin');

    $manager = Role::findByName('manager');

    $this->putJson("/api/v1/roles/{$manager->uuid}", [
        'name' => 'boss', 'description' => $manager->description,
    ])->assertStatus(422)->assertJsonValidationErrors('name');

    $this->deleteJson("/api/v1/roles/{$manager->uuid}")->assertForbidden();
    expect(Role::where('name', 'manager')->exists())->toBeTrue();
});

test('the super admin permission set cannot be modified', function () {
    rbacSignIn('admin');

    $super = Role::findByName('super_admin');

    $this->putJson("/api/v1/roles/{$super->uuid}/permissions", [
        'permissions' => ['dashboard.view'],
    ])->assertForbidden();
});

test('roles still assigned to users cannot be deleted', function () {
    rbacSignIn('admin');

    $role = Role::create(['name' => 'temp_role', 'guard_name' => 'web', 'is_system' => false]);

    $user = User::factory()->create();
    $user->assignRole('temp_role');

    $this->deleteJson("/api/v1/roles/{$role->uuid}")->assertStatus(409);

    $user->syncRoles([]);
    $this->deleteJson("/api/v1/roles/{$role->uuid}")->assertOk();
    expect(Role::where('name', 'temp_role')->exists())->toBeFalse();
});