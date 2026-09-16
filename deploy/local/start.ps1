[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

function Read-DotEnv {
    param([Parameter(Mandatory = $true)][string] $Path)

    $settings = @{}

    foreach ($line in Get-Content -LiteralPath $Path) {
        if ($line -match '^([A-Z0-9_]+)=(.*)$') {
            $settings[$matches[1]] = $matches[2].Trim('"')
        }
    }

    return $settings
}

$scriptDirectory = Split-Path -Parent $PSCommandPath
$envPath = Join-Path $scriptDirectory '.env'
$composePath = Join-Path $scriptDirectory 'compose.yaml'
$composeExecutable = 'C:\Program Files\Docker\Docker\resources\bin\docker-compose.exe'

if (-not (Test-Path -LiteralPath $envPath)) {
    throw 'Run prepare-env.ps1 before starting BRAVE.'
}

if (-not (Test-Path -LiteralPath $composeExecutable)) {
    throw "Docker Compose was not found at $composeExecutable"
}

$settings = Read-DotEnv -Path $envPath

foreach ($required in @('BRAVE_PUBLIC_URL', 'BRAVE_PUBLIC_HOST', 'VITE_MAPTILER_API_KEY')) {
    if ([string]::IsNullOrWhiteSpace($settings[$required])) {
        throw "$required is missing from deploy/local/.env."
    }

    [Environment]::SetEnvironmentVariable($required, $settings[$required], 'Process')
}

$localPort = if ([string]::IsNullOrWhiteSpace($settings['BRAVE_LOCAL_PORT'])) { '8081' } else { $settings['BRAVE_LOCAL_PORT'] }
[Environment]::SetEnvironmentVariable('BRAVE_LOCAL_PORT', $localPort, 'Process')

& $composeExecutable --env-file $envPath -f $composePath config --quiet

if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose validation failed.'
}

& $composeExecutable --env-file $envPath -f $composePath up -d --build --remove-orphans

if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose failed to start BRAVE.'
}

$deadline = (Get-Date).AddMinutes(3)

do {
    Start-Sleep -Seconds 5
    $health = docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' brave-public-local-app-1 2>$null
} while ($health -ne 'healthy' -and (Get-Date) -lt $deadline)

if ($health -ne 'healthy') {
    throw "BRAVE did not become healthy; current status: $health"
}

Write-Output "BRAVE is healthy at http://127.0.0.1:$localPort."
Write-Output "Public URL: $($settings['BRAVE_PUBLIC_URL'])"
