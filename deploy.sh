#!/usr/bin/env bash
#
# Update the application on the server. Run it as the deploy user (xash) from
# anywhere:
#
#   /var/www/rhyenigeria/deploy.sh
#
# It needs no sudo: the queue worker and Reverb are asked to restart through
# the cache, and Supervisor starts them again with the new code.

set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

# Files created here must stay writable by the www-data group.
umask 002

if [ ! -f .env ]; then
    echo "No .env file in $(pwd) - is this the deployed copy?" >&2
    exit 1
fi

# Whatever happens below, never leave the site in maintenance mode.
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Pulling the latest code"
git pull --ff-only origin main

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Building assets"
npm ci --no-audit --no-fund
npm run build

echo "==> Migrating the database"
php artisan down --retry=15
php artisan migrate --force

echo "==> Rebuilding caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan up

echo "==> Restarting the queue worker and Reverb"
php artisan queue:restart
php artisan reverb:restart

echo "==> Done: $(git log -1 --format='%h %s')"
