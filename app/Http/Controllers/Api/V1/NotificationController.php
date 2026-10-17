<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('notifications.view');

        $query = $request->user()->notifications()->latest();

        if ((string) $request->query('filter', 'all') === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(min((int) $request->query('per_page', 15), 50));

        return $this->paginated($notifications, NotificationResource::class);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('notifications.view');

        return $this->success([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $this->authorize('notifications.view');

        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification === null) {
            return $this->error('Notification not found.', 404);
        }

        $notification->markAsRead();

        return $this->success(message: 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('notifications.view');

        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(['marked' => $updated], 'All notifications marked as read.');
    }
}