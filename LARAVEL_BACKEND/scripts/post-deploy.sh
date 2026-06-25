#!/usr/bin/env bash
# Server-side steps after rsync/git pull. Run on production host only.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Installing PHP dependencies (production)..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Caching config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Signaling queue workers to restart..."
php artisan queue:restart || true

echo "==> Syncing missing AI embeddings..."
php artisan learning:sync-embeddings --missing-only --no-interaction || true
php artisan faqs:sync-embeddings --no-interaction || true
php artisan products:sync-embeddings --missing-only --no-interaction || true

echo "==> AI health check..."
php artisan ai:health-check --notify --no-interaction || true

echo "==> Post-deploy complete."
