# Backs up the shipping-management PostgreSQL database into /backups.
# Usage: powershell -ExecutionPolicy Bypass -File backup.ps1
# (Or double-click backup.bat)

$ErrorActionPreference = "Stop"

$projectDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$backupDir = Join-Path $projectDir "backups"
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

$envPath = Join-Path $projectDir ".env"
$dbHost = "127.0.0.1"
$dbPort = "5432"
$dbName = "shipping-management"
$dbUser = "postgres"
$dbPass = "postgres"

if (Test-Path $envPath) {
    foreach ($line in Get-Content $envPath) {
        if ($line -match '^\s*DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME|PASSWORD)=(.*)$') {
            switch ($Matches[1]) {
                "HOST"     { $dbHost = $Matches[2].Trim('"') }
                "PORT"     { $dbPort = $Matches[2].Trim('"') }
                "DATABASE" { $dbName = $Matches[2].Trim('"') }
                "USERNAME" { $dbUser = $Matches[2].Trim('"') }
                "PASSWORD" { $dbPass = $Matches[2].Trim('"') }
            }
        }
    }
}

$pgDumpCandidates = @(
    (Join-Path $env:ProgramFiles "PostgreSQL\18\bin\pg_dump.exe"),
    (Join-Path ${env:ProgramFiles(x86)} "PostgreSQL\18\bin\pg_dump.exe")
)
$pgDump = $pgDumpCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $pgDump) {
    throw "pg_dump.exe not found. Install PostgreSQL or update backup.ps1 with the correct path."
}

$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$file = Join-Path $backupDir "db_${dbName}_$stamp.sql"

$env:PGPASSWORD = $dbPass
& $pgDump -h $dbHost -p $dbPort -U $dbUser -d $dbName -f $file

if ($LASTEXITCODE -ne 0) {
    throw "Backup failed (pg_dump exit code $LASTEXITCODE)."
}

Write-Host "Backup created: $file"

$keep = 10
Get-ChildItem $backupDir -Filter "*.sql" | Sort-Object LastWriteTime -Descending |
    Select-Object -Skip $keep | Remove-Item -Force

Write-Host "Backups kept: $keep most recent."
