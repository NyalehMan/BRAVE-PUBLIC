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
$repositoryRoot = (Resolve-Path (Join-Path $scriptDirectory '..\..')).Path
$backendEnvPath = Join-Path $repositoryRoot 'backend\.env'
$storagePath = Join-Path $repositoryRoot 'backend\storage\app'
$backupDirectory = Join-Path $scriptDirectory 'backups'
$timestamp = (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ')
$databaseBackup = Join-Path $backupDirectory "database-$timestamp.sql"
$storageBackup = Join-Path $backupDirectory "report-storage-$timestamp.zip"
$mysqldump = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe'

if (-not (Test-Path -LiteralPath $mysqldump)) {
    throw "mysqldump was not found at $mysqldump"
}

$settings = Read-DotEnv -Path $backendEnvPath

foreach ($required in @('DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME')) {
    if ([string]::IsNullOrWhiteSpace($settings[$required])) {
        throw "$required is missing from backend/.env."
    }
}

New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null

$processInfo = [Diagnostics.ProcessStartInfo]::new()
$processInfo.FileName = $mysqldump
$processInfo.UseShellExecute = $false
$processInfo.RedirectStandardOutput = $true
$processInfo.RedirectStandardError = $true
$processInfo.CreateNoWindow = $true
$argumentValues = @(
    "--host=$($settings['DB_HOST'])"
    "--port=$($settings['DB_PORT'])"
    "--user=$($settings['DB_USERNAME'])"
    '--single-transaction'
    '--routines'
    '--events'
    '--set-gtid-purged=OFF'
    $settings['DB_DATABASE']
)
$processInfo.Arguments = ($argumentValues | ForEach-Object { '"' + $_ + '"' }) -join ' '
$processInfo.EnvironmentVariables['MYSQL_PWD'] = $settings['DB_PASSWORD']

$process = [Diagnostics.Process]::new()
$process.StartInfo = $processInfo
$null = $process.Start()
$errorRead = $process.StandardError.ReadToEndAsync()

$outputStream = [IO.File]::Create($databaseBackup)

try {
    $process.StandardOutput.BaseStream.CopyTo($outputStream)
} finally {
    $outputStream.Dispose()
}

$process.WaitForExit()
$errorText = $errorRead.GetAwaiter().GetResult()

if ($process.ExitCode -ne 0) {
    Remove-Item -LiteralPath $databaseBackup -Force -ErrorAction SilentlyContinue
    throw "mysqldump failed: $errorText"
}

Compress-Archive -Path (Join-Path $storagePath '*') -DestinationPath $storageBackup -Force

Write-Output "Database backup: $databaseBackup"
Write-Output "Report storage backup: $storageBackup"
