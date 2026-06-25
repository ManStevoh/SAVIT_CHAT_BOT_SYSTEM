# Production deploy for Laravel + Inertia (same-origin).
# Usage: .\deploy.ps1
# Prerequisites: .env configured for production (APP_URL, FRONTEND_URL, SESSION_SECURE_COOKIE, etc.)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

Write-Host "==> Installing PHP dependencies (production)..."
composer install --no-dev --optimize-autoloader --no-interaction

Write-Host "==> Installing Node dependencies..."
npm ci

Write-Host "==> Building frontend assets..."
npm run build

Write-Host "==> Running database migrations..."
php artisan migrate --force

Write-Host "==> Caching configuration and routes..."
php artisan config:cache
php artisan route:cache

Write-Host "==> Signaling queue workers to restart..."
php artisan queue:restart

Write-Host "==> Deploy complete."
Write-Host "    For CI/rsync deploys, post-deploy also runs scripts/post-deploy.sh on the server."
Write-Host "    Ensure cron runs: * * * * * php artisan schedule:run"
