# Build production bundle and create EssemChat-cPanel.zip for cPanel upload.
# Run from repo root: .\scripts\pack-cpanel.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
$src = Join-Path $root "LARAVEL_BACKEND"
$zip = Join-Path $root "EssemChat-cPanel.zip"

Set-Location $src

if (Test-Path "public/hot") { Remove-Item "public/hot" -Force }

Write-Host "==> Composer (production)..."
composer install --no-dev --optimize-autoloader --no-interaction

Write-Host "==> npm build..."
npm run build

if (-not (Test-Path "public/build/manifest.json")) {
    throw "Build failed: public/build/manifest.json missing"
}

$stagingParent = Join-Path $root "_cpanel_staging"
$staging = Join-Path $stagingParent "LARAVEL_BACKEND"
if (Test-Path $stagingParent) { Remove-Item -Recurse -Force $stagingParent }
New-Item -ItemType Directory -Path $staging -Force | Out-Null

Write-Host "==> Staging files..."
robocopy $src $staging /E /XD node_modules .git tests test-results e2e playwright-report .cursor `
    /XF .env .env.local .env.backup public\hot .phpunit.result.cache /R:1 /W:1 | Out-Null

Write-Host "==> Creating zip..."
if (Test-Path $zip) { Remove-Item $zip -Force }
tar -a -cf $zip -C $stagingParent LARAVEL_BACKEND
Remove-Item -Recurse -Force $stagingParent

$size = [math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Host "==> Done: $zip ($size MB)"
Write-Host "    Upload to cPanel and follow LARAVEL_BACKEND/CPANEL_UPLOAD_README.txt inside the zip."

Write-Host "==> Restoring dev Composer packages for local work..."
composer install --no-interaction
