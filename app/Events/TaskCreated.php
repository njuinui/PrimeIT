<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;

class TaskCreated
{
    public function __construct(
        public readonly Task $task,
        public readonly User $actor,
    ) {}
}