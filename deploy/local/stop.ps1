[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$scriptDirectory = Split-Path -Parent $PSCommandPath
$envPath = Join-Path $scriptDirectory '.env'
$composePath = Join-Path $scriptDirectory 'compose.yaml'
$composeExecutable = 'C:\Program Files\Docker\Docker\resources\bin\docker-compose.exe'

& $composeExecutable --env-file $envPath -f $composePath down

if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose failed to stop BRAVE.'
}

Write-Output 'BRAVE stopped. Existing database and report files were retained.'
