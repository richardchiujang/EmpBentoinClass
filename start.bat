@echo off
REM start.bat — 同心餐點費用申請系統啟動腳本

REM ── 1. 設定資料庫環境變數 ──
set DB_HOST=127.0.0.1
set DB_PORT=5432
set DB_NAME=tongxin_meal
set DB_USER=postgres
set DB_PASS=1234

REM ── 2. 結束占用 port 8000 的程序 ──
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8000 " ^| findstr "LISTENING"') do (
    echo [start.bat] 正在關閉舊的 port 8000 程序 PID=%%a
    taskkill /PID %%a /F >nul 2>&1
)

REM ── 3. 啟動 PHP 內建伺服器 ──
echo [start.bat] 啟動 PHP 開發伺服器 -^> http://127.0.0.1:8000
echo [start.bat] 按 Ctrl+C 停止伺服器
echo.
php -S 127.0.0.1:8000 -t src 2>nul
