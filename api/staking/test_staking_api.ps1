# ============================================
# Script de Testing: API de Staking (PowerShell)
# Descripción: Tests para los endpoints de staking
# ============================================

Write-Host "`n======================================" -ForegroundColor Cyan
Write-Host "   TESTING API DE STAKING" -ForegroundColor Cyan
Write-Host "======================================`n" -ForegroundColor Cyan

# Configuración
$API_BASE_URL = "http://localhost"
$USER_ID = 1
$TX_HASH_PREFIX = "0x" + -join ((1..64) | ForEach-Object { '{0:x}' -f (Get-Random -Maximum 16) })

# Contadores
$PASSED = 0
$FAILED = 0

# Función para tests
function Test-Endpoint {
    param(
        [string]$Name,
        [string]$Method,
        [string]$Endpoint,
        [string]$Data,
        [int]$ExpectedCode
    )
    
    Write-Host "Testing: $Name" -ForegroundColor Yellow
    
    try {
        $uri = "$API_BASE_URL$Endpoint"
        
        if ($Method -eq "GET") {
            $response = Invoke-WebRequest -Uri $uri -Method GET -UseBasicParsing
        } else {
            $headers = @{
                "Content-Type" = "application/json"
            }
            $response = Invoke-WebRequest -Uri $uri -Method $Method -Body $Data -Headers $headers -UseBasicParsing
        }
        
        $statusCode = $response.StatusCode
        $body = $response.Content | ConvertFrom-Json | ConvertTo-Json -Depth 10
        
        if ($statusCode -eq $ExpectedCode) {
            Write-Host "✓ PASS - HTTP $statusCode" -ForegroundColor Green
            Write-Host "Response: $body"
            $script:PASSED++
        } else {
            Write-Host "✗ FAIL - Expected $ExpectedCode, got $statusCode" -ForegroundColor Red
            Write-Host "Response: $body"
            $script:FAILED++
        }
    } catch {
        $statusCode = $_.Exception.Response.StatusCode.value__
        
        if ($statusCode -eq $ExpectedCode) {
            Write-Host "✓ PASS - HTTP $statusCode" -ForegroundColor Green
            $script:PASSED++
        } else {
            Write-Host "✗ FAIL - Expected $ExpectedCode, got $statusCode" -ForegroundColor Red
            Write-Host "Error: $($_.Exception.Message)"
            $script:FAILED++
        }
    }
    
    Write-Host ""
}

# ============================================
# TESTS
# ============================================

Write-Host "1. Testing GET /api/staking/get_staking_info.php" -ForegroundColor Cyan
Write-Host "--------------------------------------"
Test-Endpoint `
    -Name "Obtener info de staking (usuario válido)" `
    -Method "GET" `
    -Endpoint "/api/staking/get_staking_info.php?user_id=$USER_ID" `
    -Data "" `
    -ExpectedCode 200

Test-Endpoint `
    -Name "Obtener info de staking (usuario inválido)" `
    -Method "GET" `
    -Endpoint "/api/staking/get_staking_info.php?user_id=0" `
    -Data "" `
    -ExpectedCode 500

Write-Host "2. Testing GET /api/staking/get_staking_history.php" -ForegroundColor Cyan
Write-Host "--------------------------------------"
Test-Endpoint `
    -Name "Obtener historial (all)" `
    -Method "GET" `
    -Endpoint "/api/staking/get_staking_history.php?user_id=$USER_ID&type=all&limit=10" `
    -Data "" `
    -ExpectedCode 200

Test-Endpoint `
    -Name "Obtener historial (solo stakes)" `
    -Method "GET" `
    -Endpoint "/api/staking/get_staking_history.php?user_id=$USER_ID&type=stake&limit=10" `
    -Data "" `
    -ExpectedCode 200

Test-Endpoint `
    -Name "Obtener historial (paginación)" `
    -Method "GET" `
    -Endpoint "/api/staking/get_staking_history.php?user_id=$USER_ID&limit=5&offset=5" `
    -Data "" `
    -ExpectedCode 200

Write-Host "3. Testing GET /api/staking/get_staking_stats.php" -ForegroundColor Cyan
Write-Host "--------------------------------------"
Test-Endpoint `
    -Name "Obtener estadísticas globales" `
    -Method "GET" `
    -Endpoint "/api/staking/get_staking_stats.php" `
    -Data "" `
    -ExpectedCode 200

Test-Endpoint `
    -Name "Obtener estadísticas con usuario" `
    -Method "GET" `
    -Endpoint "/api/staking/get_staking_stats.php?user_id=$USER_ID" `
    -Data "" `
    -ExpectedCode 200

Write-Host "4. Testing POST /api/staking/stake_tokens.php" -ForegroundColor Cyan
Write-Host "--------------------------------------"
$tx1 = $TX_HASH_PREFIX + "01"
Test-Endpoint `
    -Name "Stake tokens (datos válidos)" `
    -Method "POST" `
    -Endpoint "/api/staking/stake_tokens.php" `
    -Data "{`"user_id`":$USER_ID,`"amount`":`"100.5`",`"pool_id`":0,`"tx_hash`":`"$tx1`"}" `
    -ExpectedCode 201

Test-Endpoint `
    -Name "Stake tokens (sin tx_hash)" `
    -Method "POST" `
    -Endpoint "/api/staking/stake_tokens.php" `
    -Data "{`"user_id`":$USER_ID,`"amount`":`"100.5`",`"pool_id`":0}" `
    -ExpectedCode 400

$tx2 = $TX_HASH_PREFIX + "02"
Test-Endpoint `
    -Name "Stake tokens (monto inválido)" `
    -Method "POST" `
    -Endpoint "/api/staking/stake_tokens.php" `
    -Data "{`"user_id`":$USER_ID,`"amount`":`"-10`",`"pool_id`":0,`"tx_hash`":`"$tx2`"}" `
    -ExpectedCode 500

$tx3 = $TX_HASH_PREFIX + "03"
Test-Endpoint `
    -Name "Stake tokens (pool inválido)" `
    -Method "POST" `
    -Endpoint "/api/staking/stake_tokens.php" `
    -Data "{`"user_id`":$USER_ID,`"amount`":`"100`",`"pool_id`":99,`"tx_hash`":`"$tx3`"}" `
    -ExpectedCode 500

Write-Host "5. Testing POST /api/staking/claim_rewards.php" -ForegroundColor Cyan
Write-Host "--------------------------------------"
$tx4 = $TX_HASH_PREFIX + "04"
Test-Endpoint `
    -Name "Claim rewards (datos válidos)" `
    -Method "POST" `
    -Endpoint "/api/staking/claim_rewards.php" `
    -Data "{`"user_id`":$USER_ID,`"amount`":`"5.25`",`"tx_hash`":`"$tx4`"}" `
    -ExpectedCode 200

$tx5 = $TX_HASH_PREFIX + "05"
Test-Endpoint `
    -Name "Claim rewards (sin amount)" `
    -Method "POST" `
    -Endpoint "/api/staking/claim_rewards.php" `
    -Data "{`"user_id`":$USER_ID,`"tx_hash`":`"$tx5`"}" `
    -ExpectedCode 400

Test-Endpoint `
    -Name "Claim rewards (tx duplicado)" `
    -Method "POST" `
    -Endpoint "/api/staking/claim_rewards.php" `
    -Data "{`"user_id`":$USER_ID,`"amount`":`"5.25`",`"tx_hash`":`"$tx4`"}" `
    -ExpectedCode 500

Write-Host "6. Testing POST /api/staking/unstake_tokens.php" -ForegroundColor Cyan
Write-Host "--------------------------------------"
$tx6 = $TX_HASH_PREFIX + "06"
Test-Endpoint `
    -Name "Unstake tokens (datos válidos)" `
    -Method "POST" `
    -Endpoint "/api/staking/unstake_tokens.php" `
    -Data "{`"user_id`":$USER_ID,`"amount`":`"50`",`"rewards`":`"2.5`",`"tx_hash`":`"$tx6`"}" `
    -ExpectedCode 200

$tx7 = $TX_HASH_PREFIX + "07"
Test-Endpoint `
    -Name "Unstake tokens (sin rewards)" `
    -Method "POST" `
    -Endpoint "/api/staking/unstake_tokens.php" `
    -Data "{`"user_id`":$USER_ID,`"amount`":`"25`",`"tx_hash`":`"$tx7`"}" `
    -ExpectedCode 200

# ============================================
# RESUMEN
# ============================================

Write-Host "`n======================================" -ForegroundColor Cyan
Write-Host "   RESUMEN DE TESTS" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Tests Passed: $PASSED" -ForegroundColor Green
Write-Host "Tests Failed: $FAILED" -ForegroundColor Red
Write-Host "Total Tests: $($PASSED + $FAILED)"
Write-Host ""

if ($FAILED -eq 0) {
    Write-Host "✓ Todos los tests pasaron!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "✗ Algunos tests fallaron" -ForegroundColor Red
    exit 1
}
