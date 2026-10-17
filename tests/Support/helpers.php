<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

function seedPermissions(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(PermissionSeeder::class);
}

function makeUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

function userFor(Employee|User $subject): User
{
    if ($subject instanceof User) {
        return $subject->fresh();
    }

    return $subject->user()->firstOrFail()->fresh();
}

function makeEmployee(string $role, array $attributes = []): Employee
{
    $user = makeUser($role);

    return Employee::create(array_merge([
        'employee_number' => sprintf('EMP-%04d', Employee::count() + 1),
        'first_name'      => 'Test',
        'last_name'       => 'Person '.Employee::count(),
        'status'          => 'active',
        'employment_type' => 'full_time',
        'user_id'         => $user->id,
    ], $attributes));
}

function makeOrg(): array
{
    $engineering = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
    $design      = Department::create(['name' => 'Product & Design', 'code' => 'PRD']);

    $platform   = Team::create(['department_id' => $engineering->id, 'name' => 'Platform', 'code' => 'PLT']);
    $designTeam = Team::create(['department_id' => $design->id, 'name' => 'Design Systems', 'code' => 'DSN']);

    $manager = makeEmployee('manager', [
        'first_name' => 'Mary', 'last_name' => 'Manager',
        'department_id' => $engineering->id, 'team_id' => $platform->id,
        'job_title' => 'Engineering Manager',
    ]);

    $report = makeEmployee('employee', [
        'first_name' => 'Rita', 'last_name' => 'Report',
        'department_id' => $engineering->id, 'team_id' => $platform->id,
        'job_title' => 'Backend Engineer',
        'manager_id' => $manager->id,
    ]);

    $designer = makeEmployee('employee', [
        'first_name' => 'Dana', 'last_name' => 'Designer',
        'department_id' => $design->id, 'team_id' => $designTeam->id,
        'job_title' => 'Product Designer',
    ]);

    return compact('engineering', 'design', 'platform', 'designTeam', 'manager', 'report', 'designer');
}



function makeProject(array $attributes = []): Project
{
    $owner = $attributes['owner_id'] ?? null;

    if ($owner instanceof Employee) {
        $employee = $owner;
    } elseif ($owner !== null) {
        $employee = Employee::query()->findOrFail($owner);
    } else {
        $employee = Employee::query()->first()
            ?? makeEmployee('manager');
    }

    $number = Project::count() + 1;

    return Project::create(array_merge([
        'project_number' => 'PRJ-' . str_pad(
            (string) $number,
            4,
            '0',
            STR_PAD_LEFT
        ),
        'name' => 'Project ' . $number,
        'slug' => 'project-' . $number,
        'status' => 'in_progress',
        'priority' => 'medium',
        'owner_id' => $employee->id,
        'project_manager_id' => $employee->id,
        'created_by' => $employee->user_id,
    ], $attributes));
}



function makeTask(array $attributes = []): Task
{
    $employee = Employee::query()->first() ?? makeEmployee('employee');

    return Task::query()->create(array_merge([
        'task_number' => 'TSK-'.str_pad((string) (Task::count() + 1), 4, '0', STR_PAD_LEFT),
        'title' => 'Task '.(Task::count() + 1),
        'slug' => 'task-'.(Task::count() + 1),
        'description' => 'Test task',
        'status' => 'todo',
        'priority' => 'medium',
        'created_by' => $employee->user_id,
        'start_date' => now()->startOfDay(),
        'due_date' => now()->addDays(3)->setTime(12, 0),
        'estimated_minutes' => 120,
        'actual_minutes' => 0,
        'progress' => 0,
    ], $attributes));
}

function assignTask(Task $task, Employee|User $assignee, Employee|User|null $secondaryAssignee = null): Task
{
    $employees = [$assignee instanceof User ? userFor($assignee) : $assignee];

    if ($secondaryAssignee !== null) {
        $employees[] = $secondaryAssignee instanceof User ? userFor($secondaryAssignee) : $secondaryAssignee;
    }

    $task->assignees()->sync(
        collect($employees)->mapWithKeys(fn (Employee $employee) => [
            $employee->id => [
                'assigned_by' => $task->created_by ?? $employee->user_id,
                'assigned_at' => now(),
            ],
        ])->all(),
    );

    return $task->fresh();
}