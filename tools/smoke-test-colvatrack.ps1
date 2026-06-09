param(
    [string]$BaseUrl = 'http://127.0.0.1:8000'
)

$ErrorActionPreference = 'Stop'
$Project = Split-Path -Parent $PSScriptRoot
$Php = (Get-Command php -ErrorAction Stop).Source

function Test-Url {
    param([string]$Path)

    $request = [System.Net.HttpWebRequest]::Create("$BaseUrl$Path")
    $request.AllowAutoRedirect = $false
    $request.Method = 'GET'

    try {
        $response = $request.GetResponse()
        $status = [int]$response.StatusCode
        $response.Close()
    } catch [System.Net.WebException] {
        if ($_.Exception.Response) {
            $status = [int]$_.Exception.Response.StatusCode
            $_.Exception.Response.Close()
        } else {
            return [pscustomobject]@{ Path = $Path; Status = '-'; Result = 'FAIL'; Detail = $_.Exception.Message }
        }
    }

    $result = if ($Path -in @('/login', '/forgot-password') -and $status -eq 200) { 'OK' }
        elseif ($Path -notin @('/login', '/forgot-password') -and $status -in @(302, 401, 403, 419)) { 'EXPECTED_GUARD' }
        elseif ($status -eq 200) { 'OK' }
        else { 'FAIL' }

    [pscustomobject]@{ Path = $Path; Status = $status; Result = $result; Detail = '' }
}

Write-Host 'HTTP smoke test'
@('/login', '/forgot-password', '/dashboard', '/reportes', '/auditoria') | ForEach-Object { Test-Url $_ } | Format-Table -AutoSize

Write-Host 'Laravel checks'
& $Php artisan route:list --except-vendor | Out-Null
& $Php artisan schedule:list
