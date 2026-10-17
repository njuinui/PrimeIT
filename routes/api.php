<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LoginHistoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ProjectActivityController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\ProjectMilestoneController;
use App\Http\Controllers\Api\V1\ProjectRiskController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TaskChecklistController;
use App\Http\Controllers\Api\V1\TaskCommentController;
use App\Http\Controllers\Api\V1\TaskAssigneeController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskDependencyController;
use App\Http\Controllers\Api\V1\TaskTimeEntryController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\UserController;


// Finance
use App\Http\Controllers\Api\V1\FinanceController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\PaymentController;

// Budgets
use App\Http\Controllers\Api\V1\BudgetController;

// Approvals
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\ApprovalWorkflowController;

// Meetings
use App\Http\Controllers\Api\V1\MeetingController;

// Documents
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentFolderController;
use App\Http\Controllers\Api\V1\DocumentGrantController; 


use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Version 1
|--------------------------------------------------------------------------
|
| Base URL:
|
|     /api/v1
|
| Authentication:
|
|     Authorization: Bearer <sanctum-token>
|
*/


Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {

        /*
        |--------------------------------------------------------------------------
        | Public Routes
        |--------------------------------------------------------------------------
        */

        Route::get(
            'health',
            HealthController::class
        )->name('health');


        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        |
        | Authentication-specific endpoints live under:
        |
        |     /api/v1/auth/*
        |
        */

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {

                /*
                |--------------------------------------------------------------------------
                | Public Authentication
                |--------------------------------------------------------------------------
                */

                Route::middleware('throttle:auth')->group(function (): void {

                    /*
                    | Login
                    */

                    Route::post(
                        'login',
                        [AuthController::class, 'login']
                    )->name('login');


                    /*
                    | Password
                    */

                    Route::post(
                        'password/forgot',
                        [PasswordController::class, 'forgot']
                    )->name('password.forgot');

                    Route::post(
                        'password/reset',
                        [PasswordController::class, 'reset']
                    )->name('password.reset');


                    /*
                    | Email Verification
                    */

                    Route::post(
                        'email/verify',
                        [EmailVerificationController::class, 'verify']
                    )->name('email.verify');


                    /*
                    |--------------------------------------------------------------------------
                    | Two-Factor Authentication Challenge
                    |--------------------------------------------------------------------------
                    |
                    | These endpoints are public because the user may not
                    | yet possess a fully authenticated Sanctum token.
                    |
                    */

                    Route::post(
                        'two-factor/challenge/verify',
                        [TwoFactorChallengeController::class, 'verify']
                    )->name('two-factor.challenge.verify');

                    Route::post(
                        'two-factor/challenge/recovery',
                        [TwoFactorChallengeController::class, 'recovery']
                    )->name('two-factor.challenge.recovery');
                });


                /*
                |--------------------------------------------------------------------------
                | Authenticated Authentication Actions
                |--------------------------------------------------------------------------
                |
                | These routes require:
                |
                |     auth:sanctum
                |
                */

                Route::middleware('auth:sanctum')->group(function (): void {

                    /*
                    |--------------------------------------------------------------------------
                    | Current User / Session
                    |--------------------------------------------------------------------------
                    */

                    Route::post(
                        'logout',
                        [AuthController::class, 'logout']
                    )->name('logout');

                    Route::get(
                        'me',
                        [AuthController::class, 'me']
                    )->name('me');

                    Route::post(
                        'profile/photo',
                        [AuthController::class, 'updateProfilePhoto']
                    )->name('profile.photo.update');


                    /*
                    |--------------------------------------------------------------------------
                    | Sessions / Tokens
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'sessions',
                        [AuthController::class, 'sessions']
                    )->name('sessions.index');

                    Route::post(
                        'sessions/revoke-others',
                        [AuthController::class, 'revokeOtherSessions']
                    )->name('sessions.revoke-others');

                    Route::delete(
                        'sessions/{id}',
                        [AuthController::class, 'revokeSession']
                    )
                        ->whereNumber('id')
                        ->name('sessions.destroy');


                    /*
                    |--------------------------------------------------------------------------
                    | Password
                    |--------------------------------------------------------------------------
                    */

                    Route::post(
                        'password/change',
                        [PasswordController::class, 'change']
                    )->name('password.change');


                    /*
                    |--------------------------------------------------------------------------
                    | Email Verification
                    |--------------------------------------------------------------------------
                    */

                    Route::post(
                        'email/resend-verification',
                        [EmailVerificationController::class, 'resend']
                    )
                        ->middleware('throttle:auth')
                        ->name('email.resend-verification');


                    /*
                    |--------------------------------------------------------------------------
                    | Login History
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'login-history',
                        [LoginHistoryController::class, 'index']
                    )->name('login-history.index');


                    /*
                    |--------------------------------------------------------------------------
                    | Two-Factor Authentication Management
                    |--------------------------------------------------------------------------
                    */

                    Route::get(
                        'two-factor/status',
                        [TwoFactorController::class, 'status']
                    )->name('two-factor.status');

                    Route::post(
                        'two-factor/enable',
                        [TwoFactorController::class, 'enable']
                    )->name('two-factor.enable');

                    Route::post(
                        'two-factor/confirm',
                        [TwoFactorController::class, 'confirm']
                    )->name('two-factor.confirm');

                    Route::post(
                        'two-factor/disable',
                        [TwoFactorController::class, 'disable']
                    )->name('two-factor.disable');

                    Route::post(
                        'two-factor/recovery-codes',
                        [TwoFactorController::class, 'regenerate']
                    )->name('two-factor.recovery-codes');
                });
            });


        /*
        |--------------------------------------------------------------------------
        | Authenticated Workspace
        |--------------------------------------------------------------------------
        |
        | Everything below this point requires:
        |
        |     auth:sanctum
        |
        | The authenticated workspace contains:
        |
        | - Dashboard
        | - Notifications
        | - Access Control
        | - Employees
        | - Departments
        | - Teams
        | - Projects
        | - Tasks
        |
        */

        Route::middleware('auth:sanctum')->group(function (): void {


            /*
            |--------------------------------------------------------------------------
            | Dashboard
            |--------------------------------------------------------------------------
            */

            Route::get(
                'dashboard/stats',
                [DashboardController::class, 'stats']
            )->name('dashboard.stats');


            /*
            |--------------------------------------------------------------------------
            | Notifications
            |--------------------------------------------------------------------------
            |
            | These are user/workspace resources and therefore intentionally
            | live outside the /auth prefix.
            |
            | Endpoints:
            |
            | GET  /api/v1/notifications
            | GET  /api/v1/notifications/unread-count
            | POST /api/v1/notifications/read-all
            | POST /api/v1/notifications/{id}/read
            |
            */

            Route::get(
                'notifications',
                [NotificationController::class, 'index']
            )->name('notifications.index');

            Route::get(
                'notifications/unread-count',
                [NotificationController::class, 'unreadCount']
            )->name('notifications.unread-count');

            Route::post(
                'notifications/read-all',
                [NotificationController::class, 'markAllRead']
            )->name('notifications.read-all');

            Route::post(
                'notifications/{id}/read',
                [NotificationController::class, 'markRead']
            )
                ->whereUuid('id')
                ->name('notifications.read');


            /*
            |--------------------------------------------------------------------------
            | Notification Preferences
            |--------------------------------------------------------------------------
            |
            | GET /api/v1/notification-preferences
            | PUT /api/v1/notification-preferences
            |
            */

            Route::get(
                'notification-preferences',
                [NotificationPreferenceController::class, 'index']
            )->name('notification-preferences.index');

            Route::put(
                'notification-preferences',
                [NotificationPreferenceController::class, 'update']
            )->name('notification-preferences.update');


            /*
            |--------------------------------------------------------------------------
            | Access Control
            |--------------------------------------------------------------------------
            */


            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            Route::get(
                'roles',
                [RoleController::class, 'index']
            )->name('roles.index');

            Route::post(
                'roles',
                [RoleController::class, 'store']
            )->name('roles.store');

            Route::get(
                'roles/{role}',
                [RoleController::class, 'show']
            )->name('roles.show');

            Route::put(
                'roles/{role}',
                [RoleController::class, 'update']
            )->name('roles.update');

            Route::delete(
                'roles/{role}',
                [RoleController::class, 'destroy']
            )->name('roles.destroy');

            Route::put(
                'roles/{role}/permissions',
                [RoleController::class, 'syncPermissions']
            )->name('roles.permissions');


            /*
            |--------------------------------------------------------------------------
            | Permissions
            |--------------------------------------------------------------------------
            */

            Route::get(
                'permissions',
                [PermissionController::class, 'index']
            )->name('permissions.index');


            /*
            |--------------------------------------------------------------------------
            | Users
            |--------------------------------------------------------------------------
            */

            Route::get(
                'users',
                [UserController::class, 'index']
            )->name('users.index');

            Route::put(
                'users/{user}/roles',
                [UserController::class, 'updateRoles']
            )->name('users.roles');


            /*
            |--------------------------------------------------------------------------
            | Employees
            |--------------------------------------------------------------------------
            |
            | Keep /options before /{employee} so "options" is never
            | interpreted as an employee identifier.
            |
            */

            Route::get(
                'employees/options',
                [EmployeeController::class, 'options']
            )->name('employees.options');

            Route::get(
                'employees',
                [EmployeeController::class, 'index']
            )->name('employees.index');

            Route::post(
                'employees',
                [EmployeeController::class, 'store']
            )->name('employees.store');

            Route::get(
                'employees/{employee}',
                [EmployeeController::class, 'show']
            )->name('employees.show');

            Route::put(
                'employees/{employee}',
                [EmployeeController::class, 'update']
            )->name('employees.update');

            Route::delete(
                'employees/{employee}',
                [EmployeeController::class, 'destroy']
            )->name('employees.destroy');


            /*
            |--------------------------------------------------------------------------
            | Departments
            |--------------------------------------------------------------------------
            */

            Route::get(
                'departments',
                [DepartmentController::class, 'index']
            )->name('departments.index');

            Route::post(
                'departments',
                [DepartmentController::class, 'store']
            )->name('departments.store');

            Route::put(
                'departments/{department}',
                [DepartmentController::class, 'update']
            )->name('departments.update');

            Route::delete(
                'departments/{department}',
                [DepartmentController::class, 'destroy']
            )->name('departments.destroy');


            /*
            |--------------------------------------------------------------------------
            | Teams
            |--------------------------------------------------------------------------
            */

            Route::get(
                'teams',
                [TeamController::class, 'index']
            )->name('teams.index');

            Route::post(
                'teams',
                [TeamController::class, 'store']
            )->name('teams.store');

            Route::put(
                'teams/{team}',
                [TeamController::class, 'update']
            )->name('teams.update');

            Route::delete(
                'teams/{team}',
                [TeamController::class, 'destroy']
            )->name('teams.destroy');


            /*
            |--------------------------------------------------------------------------
            | Projects
            |--------------------------------------------------------------------------
            */

            Route::get(
                'projects',
                [ProjectController::class, 'index']
            )->name('projects.index');

            Route::post(
                'projects',
                [ProjectController::class, 'store']
            )->name('projects.store');

            Route::get(
                'projects/{project}',
                [ProjectController::class, 'show']
            )->name('projects.show');

            Route::put(
                'projects/{project}',
                [ProjectController::class, 'update']
            )->name('projects.update');

            Route::delete(
                'projects/{project}',
                [ProjectController::class, 'destroy']
            )->name('projects.destroy');

            Route::get(
                'projects/{project}/tasks',
                [ProjectController::class, 'tasks']
            )->name('projects.tasks');


            /*
            |--------------------------------------------------------------------------
            | Project Members
            |--------------------------------------------------------------------------
            */

            Route::get(
                'projects/{project}/members',
                [ProjectMemberController::class, 'index']
            )->name('projects.members.index');

            Route::post(
                'projects/{project}/members',
                [ProjectMemberController::class, 'store']
            )->name('projects.members.store');

            Route::put(
                'projects/{project}/members/{employee}',
                [ProjectMemberController::class, 'update']
            )->name('projects.members.update');

            Route::delete(
                'projects/{project}/members/{employee}',
                [ProjectMemberController::class, 'destroy']
            )->name('projects.members.destroy');


            /*
            |--------------------------------------------------------------------------
            | Project Milestones
            |--------------------------------------------------------------------------
            */

            Route::get(
                'projects/{project}/milestones',
                [ProjectMilestoneController::class, 'index']
            )->name('projects.milestones.index');

            Route::post(
                'projects/{project}/milestones',
                [ProjectMilestoneController::class, 'store']
            )->name('projects.milestones.store');

            Route::put(
                'projects/{project}/milestones/{milestone}',
                [ProjectMilestoneController::class, 'update']
            )->name('projects.milestones.update');

            Route::post(
                'projects/{project}/milestones/{milestone}/toggle',
                [ProjectMilestoneController::class, 'toggle']
            )->name('projects.milestones.toggle');

            Route::delete(
                'projects/{project}/milestones/{milestone}',
                [ProjectMilestoneController::class, 'destroy']
            )->name('projects.milestones.destroy');


            /*
            |--------------------------------------------------------------------------
            | Project Risks
            |--------------------------------------------------------------------------
            */

            Route::get(
                'projects/{project}/risks',
                [ProjectRiskController::class, 'index']
            )->name('projects.risks.index');

            Route::post(
                'projects/{project}/risks',
                [ProjectRiskController::class, 'store']
            )->name('projects.risks.store');

            Route::put(
                'projects/{project}/risks/{risk}',
                [ProjectRiskController::class, 'update']
            )->name('projects.risks.update');

            Route::delete(
                'projects/{project}/risks/{risk}',
                [ProjectRiskController::class, 'destroy']
            )->name('projects.risks.destroy');


            /*
            |--------------------------------------------------------------------------
            | Project Activity
            |--------------------------------------------------------------------------
            */

            Route::get(
                'projects/{project}/activity',
                [ProjectActivityController::class, 'index']
            )->name('projects.activity');


            // ── Finance (Phase 11) ─────────────────────────────────
            Route::get('finance/stats', [FinanceController::class, 'stats'])->name('finance.stats');

            Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
            Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
            Route::put('expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
            Route::delete('expense-categories/{category}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');

            Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
            Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
            Route::post('expenses/{expense}/submit', [ExpenseController::class, 'submit'])->name('expenses.submit');
            Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
            Route::post('expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
            Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');

            Route::post('expenses/{expense}/payments', [PaymentController::class, 'store'])->name('expenses.payments.store');
            Route::delete('expenses/{expense}/payments/{payment}', [PaymentController::class, 'destroy'])->name('expenses.payments.destroy');

            Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
            Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
            Route::put('budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
            Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');


            /*
            |--------------------------------------------------------------------------
            | Tasks
            |--------------------------------------------------------------------------
            |
            | All task endpoints require an authenticated Sanctum user.
            |
            | Keep static routes such as /options before /{task}.
            |
            */

            Route::get(
                'tasks/options',
                [TaskController::class, 'options']
            )->name('tasks.options');

            Route::get(
                'tasks',
                [TaskController::class, 'index']
            )->name('tasks.index');

            Route::post(
                'tasks',
                [TaskController::class, 'store']
            )->name('tasks.store');

            Route::get(
                'tasks/{task}',
                [TaskController::class, 'show']
            )->name('tasks.show');

            Route::put(
                'tasks/{task}',
                [TaskController::class, 'update']
            )->name('tasks.update');

            Route::patch(
                'tasks/{task}/status',
                [TaskController::class, 'status']
            )->name('tasks.status');

            Route::delete(
                'tasks/{task}',
                [TaskController::class, 'destroy']
            )->name('tasks.destroy');

            Route::post(
                'tasks/{task}/watch',
                [TaskController::class, 'watch']
            )->name('tasks.watch');


            // ── Task Assignees ──────────────────────────────────────

            Route::post(
                'tasks/{task}/assignees',
                [TaskAssigneeController::class, 'store']
            )->name('tasks.assignees.attach');

            Route::delete(
                'tasks/{task}/assignees/{employee}',
                [TaskAssigneeController::class, 'destroy']
            )->name('tasks.assignees.detach');


            // ── Task Comments ───────────────────────────────────────

            Route::get(
                'tasks/{task}/comments',
                [TaskCommentController::class, 'index']
            )->name('tasks.comments.index');

            Route::post(
                'tasks/{task}/comments',
                [TaskCommentController::class, 'store']
            )->name('tasks.comments.store');

            Route::put(
                'tasks/{task}/comments/{comment}',
                [TaskCommentController::class, 'update']
            )->name('tasks.comments.update');

            Route::delete(
                'tasks/{task}/comments/{comment}',
                [TaskCommentController::class, 'destroy']
            )->name('tasks.comments.destroy');


            // ── Task Checklist ──────────────────────────────────────

            Route::get(
                'tasks/{task}/checklist',
                [TaskChecklistController::class, 'index']
            )->name('tasks.checklist.index');

            Route::post(
                'tasks/{task}/checklist',
                [TaskChecklistController::class, 'store']
            )->name('tasks.checklist.store');

            Route::put(
                'tasks/{task}/checklist/{item}',
                [TaskChecklistController::class, 'update']
            )->name('tasks.checklist.update');

            Route::post(
                'tasks/{task}/checklist/{item}/toggle',
                [TaskChecklistController::class, 'toggle']
            )->name('tasks.checklist.toggle');

            Route::delete(
                'tasks/{task}/checklist/{item}',
                [TaskChecklistController::class, 'destroy']
            )->name('tasks.checklist.destroy');


            // ── Task Dependencies ───────────────────────────────────

            Route::post(
                'tasks/{task}/dependencies',
                [TaskDependencyController::class, 'store']
            )->name('tasks.dependencies.store');

            Route::delete(
                'tasks/{task}/dependencies/{dependsOn}',
                [TaskDependencyController::class, 'destroy']
            )->name('tasks.dependencies.destroy');


            // ── Task Time Entries ───────────────────────────────────

            Route::post(
                'tasks/{task}/time-entries',
                [TaskTimeEntryController::class, 'store']
            )->name('tasks.time-entries.store');

            Route::delete(
                'tasks/{task}/time-entries/{entry}',
                [TaskTimeEntryController::class, 'destroy']
            )->name('tasks.time-entries.destroy');

            // ── Approvals (Phase 12) ───────────────────────────────
            Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
            Route::get('approvals/{approvalRequest}', [ApprovalController::class, 'show'])->name('approvals.show');
            Route::post('approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
            Route::post('approvals/{approvalRequest}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

            Route::get('approval-workflows', [ApprovalWorkflowController::class, 'index'])->name('approval-workflows.index');
            Route::post('approval-workflows', [ApprovalWorkflowController::class, 'store'])->name('approval-workflows.store');
            Route::put('approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'update'])->name('approval-workflows.update');
            Route::delete('approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'destroy'])->name('approval-workflows.destroy');
        });

        // ── Meetings (Phase 13) ────────────────────────────────
        Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
        Route::post('meetings', [MeetingController::class, 'store'])->name('meetings.store');
        Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
        Route::put('meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
        Route::delete('meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
        Route::post('meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
        Route::post('meetings/{meeting}/complete', [MeetingController::class, 'complete'])->name('meetings.complete');
        Route::post('meetings/{meeting}/respond', [MeetingController::class, 'respond'])->name('meetings.respond');
        Route::post('meetings/{meeting}/participants', [MeetingController::class, 'addParticipants'])->name('meetings.participants.store');
        Route::delete('meetings/{meeting}/participants/{employee}', [MeetingController::class, 'removeParticipant'])->name('meetings.participants.destroy');
        Route::put('meetings/{meeting}/minutes', [MeetingController::class, 'saveMinutes'])->name('meetings.minutes.save');
        Route::post('meetings/{meeting}/minutes/publish', [MeetingController::class, 'publishMinutes'])->name('meetings.minutes.publish');
        Route::post('meetings/{meeting}/minutes/convert', [MeetingController::class, 'convertActionItems'])->name('meetings.minutes.convert');

                // ── Documents (Phase 14) ───────────────────────────────
        Route::get('document-folders', [DocumentFolderController::class, 'index'])->name('document-folders.index');
        Route::post('document-folders', [DocumentFolderController::class, 'store'])->name('document-folders.store');
        Route::put('document-folders/{folder}', [DocumentFolderController::class, 'update'])->name('document-folders.update');
        Route::delete('document-folders/{folder}', [DocumentFolderController::class, 'destroy'])->name('document-folders.destroy');

        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::put('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::post('documents/{document}/versions', [DocumentController::class, 'storeVersion'])->name('documents.versions.store');
        Route::post('documents/{document}/lock', [DocumentController::class, 'lock'])->name('documents.lock');
        Route::post('documents/{document}/unlock', [DocumentController::class, 'unlock'])->name('documents.unlock');

        Route::post('documents/{document}/grants', [DocumentGrantController::class, 'store'])->name('documents.grants.store');
        Route::put('documents/{document}/grants/{user}', [DocumentGrantController::class, 'update'])->name('documents.grants.update');
        Route::delete('documents/{document}/grants/{user}', [DocumentGrantController::class, 'destroy'])->name('documents.grants.destroy');
    });
