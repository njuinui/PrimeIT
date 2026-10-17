<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class TaskBoardUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public readonly string $event,        // task.created|task.updated|task.status_changed|task.deleted|task.assignees_changed
        public readonly string $taskId,
        public readonly ?string $taskNumber,
        public readonly ?string $status,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workspace.tasks')];
    }

    public function broadcastAs(): string
    {
        return 'task.board.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event'       => $this->event,
            'task_id'     => $this->taskId,
            'task_number' => $this->taskNumber,
            'status'      => $this->status,
        ];
    }
}