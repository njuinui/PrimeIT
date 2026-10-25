#!/bin/bash
set -e

cd /var/www/html

# Ensure the Aiven CA certificate is available.
export MYSQL_ATTR_SSL_CA="${MYSQL_ATTR_SSL_CA:-/var/www/html/docker/certs/ca.pem}"

echo "===== DB TLS DIAGNOSTICS ====="
echo "DB_CONNECTION       = '${DB_CONNECTION}'"
echo "DB_HOST             = '${DB_HOST}'"
echo "DB_PORT             = '${DB_PORT}'"
echo "DB_DATABASE         = '${DB_DATABASE}'"
echo "DB_USERNAME         = '${DB_USERNAME}'"
echo "MYSQL_ATTR_SSL_CA   = '${MYSQL_ATTR_SSL_CA}'"
echo "APP_ENV             = '${APP_ENV}'"
echo "RUN_MIGRATIONS      = '${RUN_MIGRATIONS:-false}'"

if [ -r "$MYSQL_ATTR_SSL_CA" ]; then
    echo "CA certificate exists and is readable."
else
    echo "WARNING: CA certificate is missing or unreadable at: $MYSQL_ATTR_SSL_CA"
    ls -l /var/www/html/docker/certs/ 2>&1 || true
fi

# Prepare Laravel's storage link (idempotent).
php artisan storage:link --force >/dev/null 2>&1 || true

# Test the database connection BEFORE caching config.
echo "--- Database connection test ---"
DB_OK=true
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'DB CONNECTION OK' . PHP_EOL; } catch (\Throwable \$e) { fwrite(STDERR, 'DB CONNECTION FAILED: ' . \$e->getMessage() . PHP_EOL); exit(1); }" || DB_OK=false

if [ "$DB_OK" = "false" ]; then
    echo "WARNING: Database connection failed - continuing anyway."
    echo "  The container will boot, but API calls requiring the DB will 500."
    echo "  Verify Aiven service status and credentials."
else
    echo "Database connection established."
fi

# Always clear first, then cache. Cached config wins over env vars.
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

if [ "${APP_ENV:-production}" = "production" ]; then
    echo "--- Caching configuration for production ---"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache 2>/dev/null || echo "  (event:cache skipped - no events to cache)"
fi

# Run migrations only when explicitly enabled.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    if [ "$DB_OK" = "true" ]; then
        echo "--- Running migrations ---"
        php artisan migrate --force
        echo "Migrations complete."
    else
        echo "WARNING: Skipping migrations - DB connection failed earlier."
    fi
fi

# Seed admin user if requested and DB is reachable.
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ] && [ "$DB_OK" = "true" ]; then
    echo "--- Ensuring admin user exists ---"
    php artisan tinker --execute="
        use App\\Models\\User;
        use Illuminate\\Support\\Facades\\Hash;
        if (! User::where('email', getenv('ADMIN_EMAIL'))->exists()) {
            User::create([
                'name' => getenv('ADMIN_NAME') ?: 'Admin',
                'email' => getenv('ADMIN_EMAIL'),
                'password' => Hash::make(getenv('ADMIN_PASSWORD')),
                'email_verified_at' => now(),
            ]);
            echo 'Admin user created.' . PHP_EOL;
        } else {
            echo 'Admin user already exists.' . PHP_EOL;
        }
    " 2>&1 || echo "WARNING: Admin user seeding failed."
fi

echo "===== Entrypoint complete - starting Apache ====="
exec "$@"