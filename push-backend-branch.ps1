# Push Laravel backend to the same repo (SAVIT_CHAT_BOT_SYSTEM) on branch "backend"
# Run from: SAVIT_CHAT_BOT\LARAVEL_BACKEND

$ErrorActionPreference = "Stop"
$repoUrl = "https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM.git"

if (-not (Test-Path .git)) {
    Write-Host "Initializing git..."
    git init
}

$remotes = git remote 2>$null
if (-not $remotes) {
    Write-Host "Adding remote origin..."
    git remote add origin $repoUrl
} else {
    Write-Host "Remote already exists. To use same repo, ensure it points to: $repoUrl"
    git remote -v
}

Write-Host "Creating/checking out branch 'backend'..."
git checkout -b backend 2>$null
if ($LASTEXITCODE -ne 0) {
    git checkout backend
}

Write-Host "Adding all files..."
git add .

Write-Host "Committing..."
git commit -m "Backend: Laravel API" 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Nothing to commit (already clean) or no changes. Pushing existing commits."
}

Write-Host "Pushing branch 'backend' to origin..."
git push -u origin backend

Write-Host "Done. Backend is on branch 'backend' in the same repo."
