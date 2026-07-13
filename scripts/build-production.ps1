[CmdletBinding()]
param(
    [ValidatePattern('^[A-Za-z0-9._-]+$')]
    [string] $ArtifactName = 'rec-production'
)

$ErrorActionPreference = 'Stop'
$repository = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$distRoot = Join-Path $repository 'dist'
$output = Join-Path $distRoot $ArtifactName

foreach ($command in @('git', 'npm', 'composer', 'php')) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "Perintah '$command' tidak tersedia."
    }
}

Push-Location $repository
try {
    npm ci
    if ($LASTEXITCODE -ne 0) { throw 'npm ci gagal.' }
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'Vite build gagal.' }

    New-Item -ItemType Directory -Force -Path $distRoot | Out-Null
    $resolvedDist = [IO.Path]::GetFullPath($distRoot).TrimEnd('\') + '\'
    $resolvedOutput = [IO.Path]::GetFullPath($output)
    if (-not $resolvedOutput.StartsWith($resolvedDist, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Target artifact harus berada di dalam direktori dist workspace.'
    }
    if (Test-Path -LiteralPath $resolvedOutput) {
        Remove-Item -LiteralPath $resolvedOutput -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $resolvedOutput | Out-Null

    $excludedPrefixes = @(
        '.git/', '.github/', 'tests/', 'node_modules/', 'dist/',
        'resources/js/', 'resources/css/'
    )
    $excludedFiles = @(
        '.env', 'phpunit.xml', 'package.json', 'package-lock.json', 'vite.config.js'
    )
    $trackedFiles = git ls-files --cached --others --exclude-standard
    if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat membaca daftar file source.' }

    foreach ($relative in $trackedFiles) {
        $relative = ($relative -replace '\', '/').Trim()
        if ($relative -eq '' -or $excludedFiles -contains $relative) { continue }
        $excluded = $false
        foreach ($prefix in $excludedPrefixes) {
            if ($relative.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) {
                $excluded = $true
                break
            }
        }
        if ($excluded) { continue }

        $source = Join-Path $repository ($relative -replace '/', '\')
        if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { continue }
        $destination = Join-Path $resolvedOutput ($relative -replace '/', '\')
        New-Item -ItemType Directory -Force -Path (Split-Path $destination -Parent) | Out-Null
        Copy-Item -LiteralPath $source -Destination $destination
    }

    $buildSource = Join-Path $repository 'public/build'
    if (-not (Test-Path -LiteralPath (Join-Path $buildSource 'manifest.json'))) {
        throw 'Manifest Vite tidak ditemukan setelah build.'
    }
    $buildTarget = Join-Path $resolvedOutput 'public/build'
    New-Item -ItemType Directory -Force -Path $buildTarget | Out-Null
    Copy-Item -Path (Join-Path $buildSource '*') -Destination $buildTarget -Recurse -Force

    foreach ($directory in @(
        'bootstrap/cache', 'storage/app/private', 'storage/framework/cache/data',
        'storage/framework/sessions', 'storage/framework/views', 'storage/logs'
    )) {
        New-Item -ItemType Directory -Force -Path (Join-Path $resolvedOutput $directory) | Out-Null
    }

    composer --working-dir=$resolvedOutput install --no-dev --classmap-authoritative --prefer-dist --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Composer production install gagal.' }

    $metadata = [ordered]@{
        built_at_utc = [DateTime]::UtcNow.ToString('o')
        git_commit = (git rev-parse HEAD).Trim()
        php = (php -r 'echo PHP_VERSION;').Trim()
        node = (node --version).Trim()
    } | ConvertTo-Json
    Set-Content -LiteralPath (Join-Path $resolvedOutput 'ARTIFACT_BUILD.json') -Value $metadata -Encoding UTF8

    foreach ($forbidden in @('.git', 'tests', 'node_modules', 'phpunit.xml')) {
        if (Test-Path -LiteralPath (Join-Path $resolvedOutput $forbidden)) {
            throw "Artifact masih memuat '$forbidden'."
        }
    }

    Write-Output "Artifact production siap: $resolvedOutput"
    Write-Output 'Aktifkan di host dengan scripts/activate-production.ps1 setelah memasang .env dan snapshot berpasangan.'
}
finally {
    Pop-Location
}
