<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Pulse for the project "Progress" and "Design" tabs. Carries no work
 * content — clients refetch through the API, which enforces visibility.
 */
class ProjectWorkLogged implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public readonly string $projectUuid,
        public readonly string $kind,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('project.' . $this->projectUuid)];
    }

    public function broadcastAs(): string
    {
        return 'work.logged';
    }

    public function broadcastWith(): array
    {
        return ['project_id' => $this->projectUuid, 'kind' => $this->kind];
    }
}
