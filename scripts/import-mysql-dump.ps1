[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $DumpPath,

    [string] $Database = 'event_hub_local',
    [string] $MysqlExe = 'C:\xampp\mysql\bin\mysql.exe',
    [string] $HostName = '127.0.0.1',
    [int] $Port = 3306,
    [string] $Username = 'root',
    [string] $Password = '',
    [switch] $DisableForeignKeyChecks
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $MysqlExe)) {
    throw "mysql.exe non trovato: $MysqlExe. Imposta -MysqlExe con il percorso corretto della tua installazione XAMPP."
}

if (-not (Test-Path -LiteralPath $DumpPath)) {
    throw "Dump SQL non trovato: $DumpPath"
}

if ($Database -notmatch '^[A-Za-z0-9_]+$') {
    throw 'Il nome database deve contenere solo lettere, numeri e underscore.'
}

$resolvedDump = (Resolve-Path -LiteralPath $DumpPath).Path

function Invoke-MysqlClient {
    param(
        [Parameter(Mandatory = $true)]
        [string[]] $Arguments,

        [Parameter(Mandatory = $true)]
        [string] $InputFile
    )

    $processInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $processInfo.FileName = $MysqlExe
    $processInfo.UseShellExecute = $false
    $processInfo.RedirectStandardInput = $true
    $processInfo.RedirectStandardOutput = $true
    $processInfo.RedirectStandardError = $true

    foreach ($argument in $Arguments) {
        [void] $processInfo.ArgumentList.Add($argument)
    }

    $process = [System.Diagnostics.Process]::Start($processInfo)
    $stdoutTask = $process.StandardOutput.ReadToEndAsync()
    $stderrTask = $process.StandardError.ReadToEndAsync()

    try {
        $inputStream = [System.IO.File]::OpenRead($InputFile)
        try {
            $inputStream.CopyTo($process.StandardInput.BaseStream)
            $process.StandardInput.Close()
        } finally {
            $inputStream.Dispose()
        }

        $process.WaitForExit()
        $exitCode = $process.ExitCode
        $stdout = $stdoutTask.GetAwaiter().GetResult()
        $stderr = $stderrTask.GetAwaiter().GetResult()
    } finally {
        if (-not $process.HasExited) {
            $process.Kill()
        }
        $process.Dispose()
    }

    if ($stdout) {
        Write-Host $stdout
    }

    if ($exitCode -ne 0) {
        throw "mysql.exe ha terminato con codice $exitCode. Dettagli: $stderr"
    }

    if ($stderr) {
        Write-Warning $stderr
    }
}

$baseArgs = @(
    "--host=$HostName",
    "--port=$Port",
    "--user=$Username",
    '--default-character-set=utf8mb4'
)

if ($Password -ne '') {
    $baseArgs += "--password=$Password"
}

$createDatabaseSql = "CREATE DATABASE IF NOT EXISTS ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$temporarySql = New-TemporaryFile
try {
    Set-Content -LiteralPath $temporarySql -Value $createDatabaseSql -Encoding UTF8

    Write-Host "Creo/verifico il database '$Database'..."
    Invoke-MysqlClient -Arguments $baseArgs -InputFile $temporarySql

    $importArgs = $baseArgs
    if ($DisableForeignKeyChecks) {
        $importArgs += '--init-command=SET SESSION FOREIGN_KEY_CHECKS=0'
    }
    $importArgs += $Database

    Write-Host "Importo '$resolvedDump' in '$Database'..."
    Invoke-MysqlClient -Arguments $importArgs -InputFile $resolvedDump

    Write-Host "Import completato. Esegui database/mysql/verify_import.sql per controllare tabelle e relazioni."
} finally {
    Remove-Item -LiteralPath $temporarySql -Force -ErrorAction SilentlyContinue
}
