#!/bin/sh
set -e

# Railway injects real process env vars, so caching config is safe (env() in
# bootstrap/app.php still resolves TRUSTED_PROXIES from the process env).
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force

exec frankenphp php-server --root public/ --listen ":${PORT:-8080}"
