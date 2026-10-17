# PrimeIT API (Laravel)

JSON API under `/api/v1`. Authentication with Laravel Sanctum, authorisation with Spatie roles & permissions,
realtime with Laravel Reverb.

## Requirements
PHP 8.3+ with `mbstring intl gd exif bcmath zip` (+ `pdo_mysql` or `pdo_sqlite`, `redis` if using Redis), Composer.

## Setup
```bash
cp .env.example .env && composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link        # makes uploaded profile photos reachable (a fallback route exists, but the link is faster)
composer dev                    # server + queue + logs + vite + reverb
php artisan test
```

## Useful commands
| Command | Purpose |
|---|---|
| `php artisan primeit:admin` | Create a super admin or reset its password (interactive, password prompt hidden) |
| `php artisan db:seed --class=PermissionSeeder` | Re-sync built-in roles/permissions after an upgrade |
| `php artisan design:sync-figma` | Import new Figma versions for tracked design files (needs `FIGMA_TOKEN`; also scheduled every 15 min) |
| `php artisan queue:work` / `schedule:work` / `reverb:start` | The three background processes production needs |

## Features added in this release
- **Work tracking** — `work_updates` (like commits), contribution graph, per-person and per-team insights.
- **Design tracking** — Figma / Adobe XD / Sketch files with status, progress and version history.
- **Chat** — direct, group, team and project conversations with unread counts and realtime delivery.
- **Global search** — `/api/v1/search`, limited to what the signed-in user may see.
- **Photos** — uploaded profile photos are resized to ≤640 px JPEG on upload.

## Production notes
- `.env.production.example` lists every setting. `APP_DEBUG` must be `false`.
- All responses carry baseline security headers; behind a proxy set `TRUSTED_PROXIES`.
- The API is rate-limited per user (240 req/min) with stricter limits on login and chat.
- `GET /up` is the health check used by Docker.
