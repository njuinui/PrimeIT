<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\NotificationSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationSettings $settings) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('notifications.view');

        return $this->success($this->settings->forUser($request->user()));
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $this->authorize('notifications.view');

        $this->settings->sync($request->user(), $request->validated('preferences'));

        return $this->success(
            $this->settings->forUser($request->user()->fresh()),
            'Notification preferences saved.',
        );
    }
}