<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channel authorization
|--------------------------------------------------------------------------
| Echo authenticates channel subscriptions by POSTing to this route with
| the user's Sanctum token. Rules live here; enforcement is server-side.
*/

Broadcast::routes([
    'prefix'     => 'api/v1',
    'middleware' => ['auth:sanctum', 'user.relations'],
]);

// A user's own notification stream — strictly the owner, even for admins.
Broadcast::channel('App.Models.User.{uuid}', function (User $user, string $uuid): bool {
    return $user->uuid === $uuid;
});

// The shared task-board pulse. Anyone who may use the task module may
// subscribe; the pushed payload itself carries no task content.
Broadcast::channel('workspace.tasks', function (User $user): bool {
    return $user->can('tasks.view');
});