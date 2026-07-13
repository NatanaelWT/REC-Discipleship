[CmdletBinding()]
param(
    [switch] $RunMigrations
)

$ErrorActionPreference = 'Stop'
$application = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$environmentFile = Join-Path $application '.env'
if (-not (Test-Path -LiteralPath $environmentFile -PathType Leaf)) {
    throw '.env produksi belum dipasang.'
}

Push-Location $application
try {
    if ($RunMigrations) {
        php artisan migrate --force
        if ($LASTEXITCODE -ne 0) { throw 'Migration gagal; traffic jangan diaktifkan.' }
    }

    php artisan rec:schema-health
    if ($LASTEXITCODE -ne 0) { throw 'Kontrak schema belum lengkap; traffic jangan diaktifkan.' }

    php artisan optimize
    if ($LASTEXITCODE -ne 0) { throw 'Optimasi Laravel gagal.' }

    Write-Output 'Schema sehat dan cache production siap. Jalankan smoke test sebelum menonaktifkan maintenance mode.'
}
finally {
    Pop-Location
}
