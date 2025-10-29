# Script para detectar cuando MySQL se inicie y ejecutar tests automáticamente
Write-Host "`nWaiting for MySQL to start on port 3306..." -ForegroundColor Yellow
Write-Host "Press Ctrl+C to cancel`n" -ForegroundColor Gray

$mysqlDetected = $false
$checkCount = 0

while (-not $mysqlDetected) {
    $checkCount++
    
    # Check if MySQL is running on port 3306
    $result = netstat -ano | Select-String ":3306"
    
    if ($result) {
        $mysqlDetected = $true
        Write-Host "`n[SUCCESS] MySQL detected on port 3306!" -ForegroundColor Green
        Write-Host "`nStarting automated tests...`n" -ForegroundColor Cyan
        
        # Wait 2 seconds for MySQL to fully initialize
        Start-Sleep -Seconds 2
        
        # Run tests
        Write-Host "=== TEST 1: Backend API Testing ===" -ForegroundColor Cyan
        php test_api.php
        
        Write-Host "`n`n=== TEST 2: Performance Benchmarking ===" -ForegroundColor Cyan
        php benchmark_performance.php
        
        Write-Host "`n`n=== TEST 3: Database Optimization ===" -ForegroundColor Cyan
        Write-Host "Running: mysql -u root sphera < database/optimize_database.sql" -ForegroundColor Yellow
        
        if (Test-Path "database/optimize_database.sql") {
            Get-Content "database/optimize_database.sql" | mysql -u root sphera
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[OK] Database optimization complete!" -ForegroundColor Green
            } else {
                Write-Host "[ERROR] Database optimization failed!" -ForegroundColor Red
            }
        } else {
            Write-Host "[WARN] optimize_database.sql not found" -ForegroundColor Yellow
        }
        
        Write-Host "`n`n=== ALL TESTS COMPLETE ===" -ForegroundColor Green
        Write-Host "Check TEST_REPORT.md for detailed results`n" -ForegroundColor Cyan
        
    } else {
        # Show progress dots
        if ($checkCount % 5 -eq 0) {
            Write-Host "." -NoNewline -ForegroundColor Gray
        }
        Start-Sleep -Seconds 1
    }
    
    # Timeout after 5 minutes
    if ($checkCount -gt 300) {
        Write-Host "`n[TIMEOUT] MySQL not detected after 5 minutes" -ForegroundColor Red
        Write-Host "Please start MySQL manually from XAMPP" -ForegroundColor Yellow
        exit 1
    }
}
