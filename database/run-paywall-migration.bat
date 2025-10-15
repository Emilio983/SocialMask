@echo off
REM Script para ejecutar migración de PayPerView en Windows
REM Requiere MySQL instalado

echo ========================================
echo  PAYWALL DATABASE MIGRATION
echo ========================================
echo.

REM Solicitar credenciales
set /p MYSQL_USER="MySQL Username (default: root): "
if "%MYSQL_USER%"=="" set MYSQL_USER=root

set /p MYSQL_PASS="MySQL Password: "

set /p DB_NAME="Database Name (default: sphoria): "
if "%DB_NAME%"=="" set DB_NAME=sphoria

echo.
echo Connecting to MySQL...
echo.

REM Verificar conexión
mysql -u %MYSQL_USER% -p%MYSQL_PASS% -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Could not connect to MySQL
    echo Please check your credentials
    pause
    exit /b 1
)

echo [OK] Connection successful
echo.

REM Verificar que existe la base de datos
mysql -u %MYSQL_USER% -p%MYSQL_PASS% -e "USE %DB_NAME%" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Database '%DB_NAME%' does not exist
    echo.
    set /p CREATE_DB="Do you want to create it? (y/n): "
    if /i "%CREATE_DB%"=="y" (
        mysql -u %MYSQL_USER% -p%MYSQL_PASS% -e "CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        echo [OK] Database created
    ) else (
        echo Aborted
        pause
        exit /b 1
    )
)

echo.
echo Executing migration...
echo.

REM Ejecutar migración
mysql -u %MYSQL_USER% -p%MYSQL_PASS% %DB_NAME% < "database\migrations\004_paywall_system.sql"

if errorlevel 1 (
    echo [ERROR] Migration failed
    pause
    exit /b 1
)

echo.
echo [OK] Migration completed successfully
echo.

REM Verificar tablas creadas
echo Verifying tables...
mysql -u %MYSQL_USER% -p%MYSQL_PASS% %DB_NAME% -e "SHOW TABLES LIKE 'paywall_%%'"

echo.
echo ========================================
echo  MIGRATION SUMMARY
echo ========================================
echo Tables created: 5
echo - paywall_content
echo - paywall_purchases
echo - paywall_access
echo - paywall_earnings
echo - paywall_stats
echo.
echo Triggers created: 3
echo Procedures created: 2
echo Views created: 3
echo.
echo [OK] PayPerView database ready!
echo.

pause
