<?php

namespace App\Providers;

use App\Events\TaskAssigneesAdded;
use App\Events\TaskCommentAdded;
use App\Events\TaskCreated;
use App\Events\TaskStatusChanged;
use App\Listeners\SendTaskAssigneesAddedNotifications;
use App\Listeners\SendTaskCommentNotifications;
use App\Listeners\SendTaskCreatedNotifications;
use App\Listeners\SendTaskStatusChangedNotifications;
use App\Listeners\BroadcastNotificationCreated;
use Illuminate\Notifications\Events\NotificationSent;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction() && ! $this->app->runningUnitTests());

        Relation::morphMap([
            'expense' => Expense::class,
        ]);

        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        // ── Notification pipeline (Phase 8) ────────────────────
        Event::listen(TaskCreated::class, SendTaskCreatedNotifications::class);
        Event::listen(TaskAssigneesAdded::class, SendTaskAssigneesAddedNotifications::class);
        Event::listen(TaskStatusChanged::class, SendTaskStatusChangedNotifications::class);
        Event::listen(TaskCommentAdded::class, SendTaskCommentNotifications::class);
                Event::listen(NotificationSent::class, BroadcastNotificationCreated::class);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by(
            ($request->input('email') ?? $request->input('challenge_token') ?? $request->ip()).'|'.$request->ip()
        ));

        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}