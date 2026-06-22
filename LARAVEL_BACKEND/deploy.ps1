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

Write-Host "==> Deploy complete."
Write-Host "    Remember to restart queue workers and ensure cron runs: php artisan schedule:run"
