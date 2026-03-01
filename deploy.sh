#!/usr/bin/env bash
set -euo pipefail

# Usage: ./deploy.sh <deploy_path> <branch>
# Example: ./deploy.sh /var/www/quiz-staging develop

DEPLOY_PATH="${1:?Usage: $0 <deploy_path> <branch>}"
BRANCH="${2:?Usage: $0 <deploy_path> <branch>}"

echo "==> Deploying branch '$BRANCH' to '$DEPLOY_PATH'"

cd "$DEPLOY_PATH"

echo "==> Entering maintenance mode"
php artisan down --retry=60

echo "==> Pulling latest code"
git pull origin "$BRANCH"

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Installing and building frontend assets"
npm ci
npm run build

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Caching configuration"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Restarting Reverb WebSocket server"
php artisan reverb:restart

echo "==> Reloading PHP-FPM"
sudo systemctl reload php8.4-fpm

echo "==> Exiting maintenance mode"
php artisan up

echo "==> Deploy complete!"
