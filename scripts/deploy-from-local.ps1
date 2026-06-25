# Push to GitHub and trigger production deploy (GitHub Actions → SSH → essemchat subdomain).
# Usage:
#   .\scripts\deploy-from-local.ps1              # push current branch, deploy if on main
#   .\scripts\deploy-from-local.ps1 -Branch main  # merge workflow: checkout main, push, deploy
#   .\scripts\deploy-from-local.ps1 -RunTests     # run PHPUnit locally before push

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
    Write-Host "==> Triggering deploy workflow on GitHub (manual)..."
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
    Write-Host "Push to main will trigger: CI tests -> rsync -> post-deploy -> health check" -ForegroundColor Green
    Write-Host "Actions: https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM/actions"
    Write-Host "Live app: https://essemchat.essemglobalsolutions.com"
} else {
    Write-Host ""
    Write-Host "Pushed $TargetBranch. CI runs on feature branches; production deploy runs only on main." -ForegroundColor Cyan
    Write-Host "To deploy: merge to main, or run: .\scripts\deploy-from-local.ps1 -ManualDeployOnly"
}
