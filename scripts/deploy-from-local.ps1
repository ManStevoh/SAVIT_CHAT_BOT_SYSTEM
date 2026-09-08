# Push the current branch. Production deploy is automatic only after merge/push to main.
# Usage:
#   .\scripts\deploy-from-local.ps1              # push current branch (CI on PRs/feature; deploy on main)
#   .\scripts\deploy-from-local.ps1 -Branch main
#   .\scripts\deploy-from-local.ps1 -RunTests     # run PHPUnit locally before push
#   .\scripts\deploy-from-local.ps1 -ManualDeployOnly  # workflow_dispatch production deploy

param(
    [string]$Branch = "",
    [switch]$RunTests,
    [switch]$ManualDeployOnly
)

$ErrorActionPreference = "Stop"
$RepoRoot = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $RepoRoot "LARAVEL_BACKEND"

Set-Location $RepoRoot

if ($RunTests) {
    Write-Host "==> Running local tests..."
    Set-Location $Backend
    if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env"; php artisan key:generate }
    composer install --no-interaction --prefer-dist | Out-Null
    npm ci | Out-Null
    npm run typecheck
    php artisan test
    Set-Location $RepoRoot
}

$CurrentBranch = git branch --show-current
$TargetBranch = if ($Branch) { $Branch } else { $CurrentBranch }

if ($ManualDeployOnly) {
    Write-Host "==> Triggering production deploy workflow on GitHub..."
    gh workflow run deploy-production.yml --ref main
    Write-Host "Watch: https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM/actions"
    exit 0
}

if ($TargetBranch -ne $CurrentBranch) {
    Write-Host "==> Switching to branch $TargetBranch..."
    git checkout $TargetBranch
}

$Status = git status --porcelain
if ($Status) {
    Write-Host "Uncommitted changes detected. Commit first, then run this script again." -ForegroundColor Yellow
    git status -sb
    exit 1
}

Write-Host "==> Pushing $TargetBranch to origin..."
git push origin $TargetBranch

if ($TargetBranch -eq "main") {
    Write-Host ""
    Write-Host "Push to main triggers: CI tests -> /deploy/agent -> /api/health" -ForegroundColor Green
    Write-Host "Actions: https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM/actions"
    Write-Host "Live app: https://relayiq.app"
} else {
    Write-Host ""
    Write-Host "Pushed $TargetBranch. CI runs on the PR; production deploys only after merge to main." -ForegroundColor Cyan
}
