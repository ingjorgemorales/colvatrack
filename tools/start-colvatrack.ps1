param(
    [int]$AppPort = 8000,
    [int]$ReverbPort = 8080,
    [switch]$WithScheduler,
    [switch]$OpenBrowser
)

$ErrorActionPreference = 'Stop'
$Project = Split-Path -Parent $PSScriptRoot
$Php = (Get-Command php -ErrorAction Stop).Source

function Start-ColvaProcess {
    param(
        [string]$Name,
        [string[]]$Arguments
    )

    Write-Host "Starting $Name..."
    Start-Process -FilePath $Php -ArgumentList $Arguments -WorkingDirectory $Project -WindowStyle Hidden | Out-Null
}

Start-ColvaProcess -Name 'Laravel HTTP' -Arguments @('artisan', 'serve', '--host=127.0.0.1', "--port=$AppPort")
Start-ColvaProcess -Name 'Laravel Reverb' -Arguments @('artisan', 'reverb:start', '--host=127.0.0.1', "--port=$ReverbPort")

if ($WithScheduler) {
    Start-ColvaProcess -Name 'Laravel Scheduler' -Arguments @('artisan', 'schedule:work')
}

Start-Sleep -Seconds 2

$url = "http://127.0.0.1:$AppPort/login"
Write-Host "ColvaTrack disponible en $url"

if ($OpenBrowser) {
    Start-Process $url | Out-Null
}
