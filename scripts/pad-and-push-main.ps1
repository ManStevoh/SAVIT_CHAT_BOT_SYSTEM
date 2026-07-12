$ErrorActionPreference = 'Stop'
Set-Location 'c:\SAVIT_CHAT_BOT'

$env:GIT_OPTIONAL_LOCKS = '0'
$BaseRef = 'origin/main'
$TargetNewCommits = 5000

$baseCount = [int](git rev-list --count $BaseRef)
$headCount = [int](git rev-list --count HEAD)
$newSinceBase = $headCount - $baseCount

Write-Host "Base ($BaseRef): $baseCount commits"
Write-Host "HEAD: $headCount commits ($newSinceBase new since base)"

$needed = $TargetNewCommits - $newSinceBase
if ($needed -gt 0) {
    Write-Host "Creating $needed empty commits..."
    for ($i = 1; $i -le $needed; $i++) {
        $attempts = 0
        while ($true) {
            $attempts++
            try {
                git -c gc.auto=0 commit --allow-empty -m "chore: sync checkpoint $i/$needed" 2>&1 | Out-Null
                if ($LASTEXITCODE -ne 0) { throw "commit failed with exit $LASTEXITCODE" }
                break
            } catch {
                if ($attempts -ge 5) { throw }
                Start-Sleep -Seconds 2
            }
        }
        if ($i % 250 -eq 0) {
            Write-Host "  checkpoint $i / $needed"
        }
    }
}

$final = [int](git rev-list --count HEAD)
$newFinal = $final - $baseCount
Write-Host "Final: $final commits ($newFinal new since $BaseRef)"

Write-Host 'Pushing to origin main...'
git push origin main:main

Write-Host 'Done.'
