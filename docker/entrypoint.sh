#!/bin/sh
# Container start-up: prepare Laravel, then hand over to the real command.
set -e
cd /var/www/html

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Only the web container should migrate (set RUN_MIGRATIONS=true on it alone).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
