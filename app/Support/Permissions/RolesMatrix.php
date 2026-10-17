<?php

namespace App\Support\Permissions;

final class RolesMatrix
{
    /**
     * The default roles shipped with the workspace.
     * '*' = every permission in the catalog.
     *
     * @return array<int, array{name: string, description: string, is_system: bool, permissions: array<int, string>}>
     */
    public static function roles(): array
    {
        return [
            [
                'name'        => 'super_admin',
                'description' => 'Break-glass account with unrestricted access to every module and setting.',
                'is_system'   => true,
                'permissions' => ['*'],
            ],
            [
                'name'        => 'admin',
                'description' => 'Full administrative access across all modules.',
                'is_system'   => true,
                'permissions' => ['*'],
            ],
            [
                'name'        => 'manager',
                'description' => 'Runs projects and teams: full task and project management, approvals and reports.',
                'is_system'   => true,
                'permissions' => [
                    'dashboard.view', 'users.view',
                    'employees.view', 'departments.view', 'teams.view',
                    'projects.view', 'projects.view.all', 'projects.create', 'projects.update', 'projects.members.manage',
                    'tasks.view', 'tasks.view.all', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.comment',
                    'notifications.view', 'work.view', 'work.log', 'design.view', 'design.manage', 'chat.use',
                    'meetings.view', 'meetings.manage',
                    'documents.view',
                    'expenses.create',
                    'approvals.view', 'approvals.decide',
                    'reports.view', 'reports.view.all',
                ],
            ],
            [
                'name'        => 'employee',
                'description' => 'Standard collaborator: works on assigned projects and tasks, submits expenses.',
                'is_system'   => true,
                'permissions' => [
                    'dashboard.view',
                    'employees.view', 'departments.view', 'teams.view',
                    'projects.view',
                    'tasks.view', 'tasks.create', 'tasks.update', 'tasks.comment',
                    'notifications.view', 'work.view', 'work.log', 'design.view', 'design.manage', 'chat.use',
                    'meetings.view',
                    'documents.view',
                    'expenses.create',
                ],
            ],
            [
                'name'        => 'finance_manager',
                'description' => 'Owns the finance module: expenses, budgets, payments and financial reports.',
                'is_system'   => true,
                'permissions' => [
                    'dashboard.view',
                    'employees.view', 'departments.view', 'teams.view',
                    'projects.view', 'tasks.view',
                    'notifications.view', 'chat.use',
                    'finance.view', 'finance.view.all', 'expenses.create', 'expenses.approve',
                    'budgets.manage', 'payments.manage',
                    'approvals.view', 'approvals.decide',
                    'reports.view', 'reports.view.all',
                    'meetings.view', 'documents.view',
                ],
            ],
            [
                'name'        => 'hr',
                'description' => 'People operations: manages users, employees, departments and teams.',
                'is_system'   => true,
                'permissions' => [
                    'dashboard.view',
                    'users.view', 'users.create', 'users.update', 'users.delete',
                    'employees.view', 'employees.create', 'employees.update', 'employees.delete',
                    'departments.view', 'departments.manage',
                    'teams.view', 'teams.manage',
                    'notifications.view', 'chat.use',
                    'meetings.view', 'documents.view',
                    'reports.view',
                ],
            ],
        ];
    }
}