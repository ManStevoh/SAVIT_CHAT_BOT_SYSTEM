#!/usr/bin/env bash
# Production deploy for Laravel + Inertia (same-origin).
# Usage: ./deploy.sh
# Prerequisites: .env configured for production (APP_URL, FRONTEND_URL, SESSION_SECURE_COOKIE, etc.)

set -euo pipefail

cd "$(dirname "$0")"

echo "==> Installing PHP dependencies (production)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Installing Node dependencies..."
npm ci

echo "==> Building frontend assets..."
npm run build

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Caching configuration and routes..."
php artisan config:cache
php artisan route:cache

echo "==> Signaling queue workers to restart..."
php artisan queue:restart || true

echo "==> Deploy complete."
echo "    For CI/rsync deploys, post-deploy also runs scripts/post-deploy.sh on the server."
echo "    Ensure cron runs: * * * * * php artisan schedule:run"
