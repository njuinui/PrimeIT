<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;

class TaskStatusChanged
{
    public function __construct(
        public readonly Task $task,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly User $actor,
    ) {}
}