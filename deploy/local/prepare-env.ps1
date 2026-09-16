[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^https://[A-Za-z0-9.-]+\.ts\.net/?$')]
    [string] $PublicUrl,

    [ValidateRange(1024, 65535)]
    [int] $LocalPort = 8081
)

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
$repositoryRoot = (Resolve-Path (Join-Path $scriptDirectory '..\..')).Path
$backendEnvPath = Join-Path $repositoryRoot 'backend\.env'
$frontendEnvPath = Join-Path $repositoryRoot 'frontend\.env.local'
$outputPath = Join-Path $scriptDirectory '.env'

if (-not (Test-Path -LiteralPath $backendEnvPath)) {
    throw "Missing $backendEnvPath"
}

if (-not (Test-Path -LiteralPath $frontendEnvPath)) {
    throw "Missing $frontendEnvPath"
}

$backendSettings = Read-DotEnv -Path $backendEnvPath
$frontendSettings = Read-DotEnv -Path $frontendEnvPath
$mapTilerKey = $frontendSettings['VITE_MAPTILER_API_KEY']

if ([string]::IsNullOrWhiteSpace($mapTilerKey)) {
    $mapTilerKey = $frontendSettings['VITE_MAPTILER_KEY']
}

if ([string]::IsNullOrWhiteSpace($backendSettings['APP_KEY'])) {
    throw 'APP_KEY is missing from backend/.env.'
}

if ([string]::IsNullOrWhiteSpace($mapTilerKey)) {
    throw 'VITE_MAPTILER_API_KEY/VITE_MAPTILER_KEY is missing from frontend/.env.local.'
}

$uri = [Uri]$PublicUrl
$normalisedUrl = "https://$($uri.Host)"
$lines = @(
    "BRAVE_PUBLIC_URL=$normalisedUrl"
    "BRAVE_PUBLIC_HOST=$($uri.Host)"
    "BRAVE_LOCAL_PORT=$LocalPort"
    "VITE_MAPTILER_API_KEY=$mapTilerKey"
)

[IO.File]::WriteAllLines($outputPath, $lines, [Text.UTF8Encoding]::new($false))

Write-Output "Created the private local deployment configuration at $outputPath."
Write-Output "Public API URL: $normalisedUrl"
