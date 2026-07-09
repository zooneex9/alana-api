#!/bin/bash
set -e

# Laravel Forge deploy script for Alana API (API-only — no Vite/npm build required).
# Paste this in Forge → Site → Deploy Script.

cd $FORGE_SITE_PATH

git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --optimize-autoloader --no-interaction

if [ -f artisan ]; then
    php artisan migrate --force
    php artisan storage:link 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan queue:restart 2>/dev/null || true
fi

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

echo "Deploy complete."
