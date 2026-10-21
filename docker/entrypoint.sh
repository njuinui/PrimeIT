
#!/bin/sh
set -e

cd /var/www/html

# Ensure the Aiven CA certificate is available.
export MYSQL_ATTR_SSL_CA="${MYSQL_ATTR_SSL_CA:-/var/www/html/docker/certs/ca.pem}"

echo "===== DB TLS DIAGNOSTICS ====="
echo "DB_CONNECTION = '${DB_CONNECTION}'"
echo "DB_HOST       = '${DB_HOST}'"
echo "DB_PORT       = '${DB_PORT}'"
echo "MYSQL_ATTR_SSL_CA = '${MYSQL_ATTR_SSL_CA}'"

if [ -r "$MYSQL_ATTR_SSL_CA" ]; then
    echo "CA certificate exists and is readable."
else
    echo "ERROR: CA certificate is missing or unreadable."
    ls -l /var/www/html/docker/certs/ 2>&1 || true
fi

# Prepare Laravel's storage link.
php artisan storage:link --force >/dev/null 2>&1 || true

# Test the database before caching configuration.
echo "--- Database connection test ---"
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'DB CONNECTION OK'; } catch (\Throwable \$e) { fwrite(STDERR, 'DB CONNECTION FAILED: '.\$e->getMessage().PHP_EOL); exit(1); }"

# Cache production configuration only after the checks.
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Run migrations only when explicitly enabled.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
