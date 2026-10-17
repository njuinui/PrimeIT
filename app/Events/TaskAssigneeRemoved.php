<?php

namespace App\Events;

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;

class TaskAssigneeRemoved
{
    public function __construct(
        public readonly Task $task,
        public readonly Employee $employee,
        public readonly User $actor,
    ) {}
}