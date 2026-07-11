$ErrorActionPreference = 'Stop'
Set-Location 'c:\SAVIT_CHAT_BOT'
$ExcludePattern = 'EssemChat-cPanel\.zip|\.phpunit\.result\.cache|database\.sqlite|storage[\\/]logs|storage[\\/]framework[\\/]testing|\.jpg$|\.jpeg$|\.png$|\.gif$|\.webp$|bulk-commit\.log|bulk-granular-commit\.ps1|finish-remaining-commits\.ps1'

git status --porcelain -uall | ForEach-Object {
    $line = $_.TrimEnd("`r")
    if ($line.Length -lt 4) { return }
    $path = $line.Substring(3).Trim('"')
    if ($path -match $ExcludePattern) { return }
    if (-not (Test-Path $path -PathType Leaf)) { return }

    $status = $line.Substring(0, 2).Trim()
    $action = if ($status -eq '??' -or $status -eq 'A') { 'Add' } else { 'Update' }
    $name = Split-Path $path -Leaf
    $dir = Split-Path $path -Parent

    git add -- "$path"
    git commit -m "$action $name in $dir" | Out-Null
    Write-Host "Committed $path"
}

$since = git rev-list --count e91505f..HEAD
Write-Host "New commits since e91505f: $since"
