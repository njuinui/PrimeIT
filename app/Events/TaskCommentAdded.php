<?php

namespace App\Events;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

class TaskCommentAdded
{
    public function __construct(
        public readonly Task $task,
        public readonly TaskComment $comment,
        public readonly User $actor,
    ) {}
}