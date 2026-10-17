<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('health endpoint reports service and database status', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.database', 'connected');
});

test('dashboard stats require authentication and the dashboard.view permission', function () {
    $this->getJson('/api/v1/dashboard/stats')->assertUnauthorized();

    $this->seed(PermissionSeeder::class);

    $roleless = User::factory()->create();
    Sanctum::actingAs($roleless);
    $this->getJson('/api/v1/dashboard/stats')->assertForbidden();

    $employee = User::factory()->create();
    $employee->assignRole('employee');
    Sanctum::actingAs($employee->fresh());

    $this->getJson('/api/v1/dashboard/stats')->assertOk();
});

test('dashboard stats return real workspace metrics', function () {
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('employee');
    Sanctum::actingAs($user->fresh());

    Project::create([
        'project_number' => 'PRJ-0001',
        'name'           => 'Test Project',
        'slug'           => 'test-project',
        'status'         => 'in_progress',
        'progress'       => 40,
    ]);

    Task::create(['task_number' => 'TSK-0001', 'title' => 'First task',  'slug' => 'first-task',  'status' => 'completed']);
    Task::create(['task_number' => 'TSK-0002', 'title' => 'Second task', 'slug' => 'second-task', 'status' => 'in_progress', 'due_date' => now()->subDay()]);

    $this->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.stats.projects', 1)
        ->assertJsonPath('data.stats.tasks', 2)
        ->assertJsonPath('data.stats.completion_rate', 50)
        ->assertJsonPath('data.stats.overdue_tasks', 1);
});