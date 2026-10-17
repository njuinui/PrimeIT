<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResetAuthState;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
// use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every /api/* request speaks JSON.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            ResetAuthState::class,
        ]);

        // Behind a load balancer / reverse proxy (Render, Nginx, Cloudflare…) the real
        // scheme and client IP come from forwarded headers. Set TRUSTED_PROXIES=* or a
        // comma-separated list of proxy IPs in production.
        $trusted = env('TRUSTED_PROXIES');
        if ($trusted !== null && $trusted !== '') {
            $middleware->trustProxies(at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)));
        }

        // Baseline browser hardening on every response, and the per-user API rate limit.
        $middleware->append(SecurityHeaders::class);
        $middleware->throttleApi();

        // RBAC middleware aliases.
        $middleware->alias([
            'user.relations' => \App\Http\Middleware\EagerLoadUserRelations::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One consistent JSON error envelope for the whole API.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiExceptionRenderer::render($e, $request);
            }

            return null;
        });
    })
    ->create();
