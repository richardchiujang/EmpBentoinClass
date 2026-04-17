# start.ps1 - Tongxin Meal System startup script
# Usage: powershell -ExecutionPolicy Bypass -File .\start.ps1

# 1. Set DB environment variables
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '5432'
$env:DB_NAME = 'tongxin_meal'
$env:DB_USER = 'postgres'
$env:DB_PASS = '1234'

# 2. Kill any process already listening on port 8000
$existing = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($existing) {
    $pid8000 = ($existing | Select-Object -First 1).OwningProcess
    Stop-Process -Id $pid8000 -Force -ErrorAction SilentlyContinue
    Write-Host "[start.ps1] Stopped old port 8000 process (PID $pid8000)"
}

# 3. Start PHP built-in server (redirect stderr to null to suppress NativeCommandError)
Write-Host '[start.ps1] Starting PHP dev server -> http://127.0.0.1:8000'
Write-Host '[start.ps1] Press Ctrl+C to stop'
Write-Host ''
php -S 127.0.0.1:8000 -t src 2>$null
