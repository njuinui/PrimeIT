<?php

namespace App\Support\Permissions;

final class Catalog
{
    /**
     * Every permission in the workspace, grouped for humans.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'View the personal workspace dashboard',
            ],

            'People' => [
                'users.view'         => 'View user accounts and their roles',
                'users.create'       => 'Create user accounts',
                'users.update'       => 'Update users and manage their roles',
                'users.delete'       => 'Delete user accounts',
                'employees.view'     => 'View the employee directory',
                'employees.create'   => 'Create employee records',
                'employees.update'   => 'Update employee records',
                'employees.delete'   => 'Delete employee records',
                'departments.view'   => 'View departments',
                'departments.manage' => 'Create, update and delete departments',
                'teams.view'         => 'View teams',
                'teams.manage'       => 'Create, update and delete teams',
            ],

            'Projects' => [
                'projects.view'           => 'View assigned projects',
                'projects.view.all'       => 'View every project in the company',
                'projects.create'         => 'Create projects',
                'projects.update'         => 'Update projects they can see',
                'projects.delete'         => 'Delete projects',
                'projects.members.manage' => 'Add and remove project members',
            ],

            'Tasks' => [
                'tasks.view'     => 'View assigned tasks',
                'tasks.view.all' => 'View every task in the company',
                'tasks.create'   => 'Create tasks',
                'tasks.update'   => 'Update tasks they can see',
                'tasks.delete'   => 'Delete tasks',
                'tasks.assign'   => 'Assign tasks to other people',
                'tasks.comment'  => 'Comment on tasks',
            ],

            'Notifications' => [
                'notifications.view' => 'Access the notification center',
            ],

            'Finance' => [
                'finance.view'     => 'View own financial data (expenses, payments)',
                'finance.view.all' => 'View company-wide financial data',
                'expenses.create'  => 'Submit expenses',
                'expenses.approve' => 'Approve or reject expense requests',
                'budgets.manage'   => 'Manage project and department budgets',
                'payments.manage'  => 'Record and manage payments',
            ],

            'Approvals' => [
                'approvals.view'    => 'View approval requests',
                'approvals.decide'  => 'Approve or reject requests in the workflow',
                'approvals.manage'  => 'Create and manage approval workflows',
            ],

            'Meetings' => [
                'meetings.view'   => 'View meetings they are invited to',
                'meetings.manage' => 'Create, update and cancel meetings',
            ],

            'Documents' => [
                'documents.view'   => 'Browse the document library',
                'documents.manage' => 'Upload, version and share documents',
            ],

            'Reports' => [
                'reports.view'     => 'View own productivity and task reports',
                'reports.view.all' => 'View company-wide reports',
            ],

            'Administration' => [
                'roles.view'      => 'View roles and the permission catalog',
                'roles.manage'    => 'Create, update and delete roles',
                'settings.manage' => 'Change company, email and security settings',
                'audit.view'      => 'Read the system audit log',
            ],
        ];
    }

    /**
     * Flat list of every permission name.
     *
     * @return string[]
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::groups() as $permissions) {
            foreach (array_keys($permissions) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }
}