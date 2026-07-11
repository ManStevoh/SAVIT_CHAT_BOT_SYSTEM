#!/usr/bin/env bash
# Server-side steps after rsync/git pull. Run on production host only.
set -euo pipefail

cd "$(dirname "$0")/.."

# Dev hot file must never exist in production (causes browser to load [::1]:5173).
if [ -f public/hot ]; then
  echo "==> Removing stale public/hot (Vite dev server marker)..."
  rm -f public/hot
fi

if [ ! -f public/build/manifest.json ]; then
  echo "ERROR: public/build/manifest.json is missing. Frontend was not built."
  if command -v npm >/dev/null 2>&1; then
    echo "==> Attempting npm ci && npm run build..."
    npm ci
    npm run build
  else
    echo "Install Node 20+ on the server, or run 'npm run build' locally and upload public/build/."
    exit 1
  fi
fi

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
