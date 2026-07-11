# Granular commit script — builds new files in line chunks; one commit per modified file.
$ErrorActionPreference = 'Stop'
Set-Location 'c:\SAVIT_CHAT_BOT'

$ChunkSize = 6
$ExcludePattern = 'EssemChat-cPanel\.zip|\.phpunit\.result\.cache|database\.sqlite|storage[\\/]logs|storage[\\/]framework[\\/]testing|\.jpg$|\.jpeg$|\.png$|\.gif$|\.webp$'

function Should-Skip([string]$Path) {
    return $Path -match $ExcludePattern
}

function Get-CommitFiles {
    git status --porcelain -uall | ForEach-Object {
        $line = $_.TrimEnd("`r")
        if ($line.Length -lt 4) { return }
        $path = $line.Substring(3).Trim('"')
        if (Should-Skip $path) { return }
        if (-not (Test-Path $path -PathType Leaf)) { return }
        [PSCustomObject]@{
            Status = $line.Substring(0, 2).Trim()
            Path   = $path
        }
    } | Sort-Object Path
}

function Commit-Message([string]$Path, [string]$Action, [int]$Part = 0, [int]$Total = 0) {
    $base = Split-Path $Path -Leaf
    $dir = Split-Path $Path -Parent
    if ($Part -gt 0) {
        return "$Action $base (part $Part/$Total) in $dir"
    }
    return "$Action $base in $dir"
}

function Commit-NewFileInChunks([string]$FilePath) {
    $finalPath = "$FilePath.__bulk_final__"
    Copy-Item -LiteralPath $FilePath -Destination $finalPath -Force
    Remove-Item -LiteralPath $FilePath -Force

    $lines = Get-Content -LiteralPath $finalPath -Encoding UTF8
    if ($null -eq $lines) { $lines = @() }
    if ($lines -is [string]) { $lines = @($lines) }

    $built = New-Object System.Collections.Generic.List[string]
    $part = 0
    $totalParts = [Math]::Max(1, [Math]::Ceiling($lines.Count / $ChunkSize))

    for ($i = 0; $i -lt $lines.Count; $i++) {
        $built.Add($lines[$i])
        $isChunkEnd = (($i + 1) % $ChunkSize -eq 0) -or ($i -eq ($lines.Count - 1))
        if (-not $isChunkEnd) { continue }

        $part++
        Set-Content -LiteralPath $FilePath -Value $built.ToArray() -Encoding UTF8 -NoNewline:$false
        git add -- "$FilePath"
        $msg = Commit-Message -Path $FilePath -Action 'Add' -Part $part -Total $totalParts
        git commit -m $msg | Out-Null
    }

    if ($lines.Count -eq 0) {
        New-Item -ItemType File -Path $FilePath -Force | Out-Null
        git add -- "$FilePath"
        git commit -m (Commit-Message -Path $FilePath -Action 'Add empty') | Out-Null
    }

    Remove-Item -LiteralPath $finalPath -Force -ErrorAction SilentlyContinue
}

function Commit-ModifiedFile([string]$FilePath) {
    git add -- "$FilePath"
    $msg = Commit-Message -Path $FilePath -Action 'Update'
    git commit -m $msg | Out-Null
}

$files = Get-CommitFiles
$newFiles = $files | Where-Object { $_.Status -eq '??' -or $_.Status -eq 'A' }
$modFiles = $files | Where-Object { $_.Status -eq 'M' -or $_.Status -eq 'MM' -or $_.Status -eq 'AM' }

Write-Host "Committing $($newFiles.Count) new files in ~$ChunkSize-line chunks and $($modFiles.Count) modified files..."

foreach ($f in $newFiles) {
    Commit-NewFileInChunks -FilePath $f.Path
}

foreach ($f in $modFiles) {
    Commit-ModifiedFile -FilePath $f.Path
}

$count = (git rev-list --count HEAD)
Write-Host "Done. Total commits on branch: $count"
