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

# ---- TEMPORARY DB TLS DIAGNOSTICS (remove once connection works) ----
echo "===== DB TLS DIAGNOSTICS ====="
echo "MYSQL_ATTR_SSL_CA = '${MYSQL_ATTR_SSL_CA}'"
echo "DB_CONNECTION     = '${DB_CONNECTION}'"
echo "DB_HOST           = '${DB_HOST}'  DB_PORT='${DB_PORT}'"
echo "--- file at that path? ---"
ls -l "${MYSQL_ATTR_SSL_CA}" 2>&1 || echo "FILE NOT FOUND"
echo "--- does docker/certs exist in image? ---"
ls -l /var/www/html/docker/certs/ 2>&1 || echo "docker/certs NOT FOUND"
echo "--- what Laravel actually loaded for mysql.options ---"
php artisan tinker --execute="var_dump(config('database.connections.mysql.options'));" 2>&1 || true
echo "--- raw PDO connect test (non-fatal) ---"
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'DB OK'; } catch (\Throwable \$e) { echo 'FAIL: '.\$e->getMessage(); }" 2>&1 || true
echo "===== END DIAGNOSTICS ====="
# ---------------------------------------------------------------------

# Only the web container should migrate (set RUN_MIGRATIONS=true on it alone).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"