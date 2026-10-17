<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Delivered on every participant's private user channel (the same stream
 * notifications use), so one socket subscription covers all conversations.
 */
class MessageSent implements ShouldBroadcastNow
{
    /**
     * @param array<string, mixed> $payload   ChatPayload::message() output
     * @param string[]             $recipientUuids  user uuids of all participants
     */
    public function __construct(
        public readonly array $payload,
        public readonly array $recipientUuids,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn (string $uuid) => new PrivateChannel('App.Models.User.' . $uuid),
            $this->recipientUuids,
        );
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
