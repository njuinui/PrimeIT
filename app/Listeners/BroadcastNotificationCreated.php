<?php

namespace App\Listeners;

use App\Events\Realtime\RealtimeNotificationCreated;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

class BroadcastNotificationCreated implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationSent $event): void
    {
        // We only want to broadcast notifications
        // that were successfully stored in the database.
        if ($event->channel !== 'database') {
            return;
        }

        // NotificationSent::$notifiable is mixed,
        // so make sure it is actually our User model.
        $user = $event->notifiable;

        if (! $user instanceof User) {
            return;
        }

        try {
            // Convert the notification into the same payload
            // structure used by the frontend.
            $data = $event->notification->toArray($user);

            $data['read_at'] = null;
            $data['created_at'] = now()->toISOString();

            // Use the User primary key consistently with
            // the private channel: App.Models.User.{id}
            broadcast(
                new RealtimeNotificationCreated(
                    (string) $user->id,
                    $data
                )
            );
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast realtime notification.', [
                'user_id' => $user->id,
                'notification' => get_class($event->notification),
                'channel' => $event->channel,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}