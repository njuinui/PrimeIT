<?php

namespace App\Support\Notifications;

final class Categories
{
    public const TASK_ASSIGNED       = 'task_assigned';
    public const TASK_STATUS_CHANGED = 'task_status_changed';
    public const TASK_MENTIONED      = 'task_mentioned';
    public const TASK_COMMENTED      = 'task_commented';
    public const TASK_REMINDER       = 'task_reminder';     // Phase 9
    public const PROJECT_UPDATES     = 'project_updates';   // reserved
    public const SYSTEM              = 'system';
    public const EXPENSE_SUBMITTED = 'expense_submitted';
    public const EXPENSE_DECIDED   = 'expense_decided';
    public const EXPENSE_PAID      = 'expense_paid';
    public const APPROVAL_REQUESTED  = 'approval_requested';
    public const APPROVAL_DECIDED    = 'approval_decided';
    public const APPROVAL_ESCALATED  = 'approval_escalated';
    public const MEETING_INVITED   = 'meeting_invited';
    public const MEETING_CANCELLED = 'meeting_cancelled';
    public const MEETING_MINUTES   = 'meeting_minutes';
    public const MEETING_REMINDER  = 'meeting_reminder';
    public const DOCUMENT_SHARED     = 'document_shared';
    public const DOCUMENT_NEW_VERSION = 'document_new_version';

    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            self::TASK_ASSIGNED       => 'Your tasks — new assignments',
            self::TASK_STATUS_CHANGED => 'Your tasks — status changes made by others',
            self::TASK_MENTIONED      => 'You are @-mentioned in a comment',
            self::TASK_COMMENTED      => 'Comments on tasks you follow',
            self::TASK_REMINDER       => 'Deadline reminders (7/3/2/1 days, due today, overdue)',
            self::PROJECT_UPDATES     => 'Project membership and milestone updates',
            self::SYSTEM              => 'System and account messages',
            self::EXPENSE_SUBMITTED => 'Expenses awaiting your approval',
            self::EXPENSE_DECIDED   => 'Your expenses — approval decisions',
            self::EXPENSE_PAID      => 'Your expenses — payments recorded',
            self::APPROVAL_REQUESTED => 'Approval requests waiting on you',
            self::APPROVAL_DECIDED   => 'Your approval requests — outcomes',
            self::APPROVAL_ESCALATED => 'Approvals escalated to you',
            self::MEETING_INVITED   => 'Meeting invitations',
            self::MEETING_CANCELLED => 'Meeting cancellations',
            self::MEETING_MINUTES   => 'Meeting minutes published',
            self::MEETING_REMINDER  => 'Meeting reminders (24h and 15min before)',
            self::DOCUMENT_SHARED     => 'Documents shared with you',
            self::DOCUMENT_NEW_VERSION => 'New versions of documents you can access',
        ];
    }

    /** @return array<string, array{in_app: bool, email: bool}> */
    public static function defaults(): array
    {
        return [
            self::TASK_ASSIGNED       => ['in_app' => true, 'email' => true],
            self::TASK_STATUS_CHANGED => ['in_app' => true, 'email' => false],
            self::TASK_MENTIONED      => ['in_app' => true, 'email' => true],
            self::TASK_COMMENTED      => ['in_app' => true, 'email' => false],
            self::TASK_REMINDER       => ['in_app' => true, 'email' => true],
            self::PROJECT_UPDATES     => ['in_app' => true, 'email' => false],
            self::SYSTEM              => ['in_app' => true, 'email' => true],
            self::EXPENSE_SUBMITTED => ['in_app' => true, 'email' => true],
            self::EXPENSE_DECIDED   => ['in_app' => true, 'email' => true],
            self::EXPENSE_PAID      => ['in_app' => true, 'email' => false],
            self::APPROVAL_REQUESTED => ['in_app' => true, 'email' => true],
            self::APPROVAL_DECIDED   => ['in_app' => true, 'email' => true],
            self::APPROVAL_ESCALATED => ['in_app' => true, 'email' => true],
            self::MEETING_INVITED   => ['in_app' => true, 'email' => true],
            self::MEETING_CANCELLED => ['in_app' => true, 'email' => true],
            self::MEETING_MINUTES   => ['in_app' => true, 'email' => false],
            self::MEETING_REMINDER  => ['in_app' => true, 'email' => true],
            self::DOCUMENT_SHARED     => ['in_app' => true, 'email' => true],
            self::DOCUMENT_NEW_VERSION => ['in_app' => true, 'email' => false],
        ];
    }
}
