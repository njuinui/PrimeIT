<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

class TaskAssigneesAdded
{
    public function __construct(
        public readonly Task $task,
        public readonly Collection $employees,
        public readonly User $actor,
    ) {}
}