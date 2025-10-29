#!/bin/bash

# ============================================
# Script de Testing: API de Staking
# Descripción: Tests para los endpoints de staking
# ============================================

echo "======================================"
echo "   TESTING API DE STAKING"
echo "======================================"
echo ""

# Configuración
API_BASE_URL="http://localhost"
USER_ID=1
TX_HASH_PREFIX="0x$(openssl rand -hex 32)"

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Contador de tests
PASSED=0
FAILED=0

# Función para tests
test_endpoint() {
    local name=$1
    local method=$2
    local endpoint=$3
    local data=$4
    local expected_code=$5
    
    echo -e "${YELLOW}Testing:${NC} $name"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" "$API_BASE_URL$endpoint")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method \
            -H "Content-Type: application/json" \
            -d "$data" \
            "$API_BASE_URL$endpoint")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ "$http_code" = "$expected_code" ]; then
        echo -e "${GREEN}✓ PASS${NC} - HTTP $http_code"
        echo "Response: $body" | jq '.' 2>/dev/null || echo "$body"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC} - Expected $expected_code, got $http_code"
        echo "Response: $body"
        ((FAILED++))
    fi
    echo ""
}

echo "1. Testing GET /api/staking/get_staking_info.php"
echo "--------------------------------------"
test_endpoint \
    "Obtener info de staking (usuario válido)" \
    "GET" \
    "/api/staking/get_staking_info.php?user_id=$USER_ID" \
    "" \
    "200"

test_endpoint \
    "Obtener info de staking (usuario inválido)" \
    "GET" \
    "/api/staking/get_staking_info.php?user_id=0" \
    "" \
    "500"

echo "2. Testing GET /api/staking/get_staking_history.php"
echo "--------------------------------------"
test_endpoint \
    "Obtener historial (all)" \
    "GET" \
    "/api/staking/get_staking_history.php?user_id=$USER_ID&type=all&limit=10" \
    "" \
    "200"

test_endpoint \
    "Obtener historial (solo stakes)" \
    "GET" \
    "/api/staking/get_staking_history.php?user_id=$USER_ID&type=stake&limit=10" \
    "" \
    "200"

test_endpoint \
    "Obtener historial (paginación)" \
    "GET" \
    "/api/staking/get_staking_history.php?user_id=$USER_ID&limit=5&offset=5" \
    "" \
    "200"

echo "3. Testing GET /api/staking/get_staking_stats.php"
echo "--------------------------------------"
test_endpoint \
    "Obtener estadísticas globales" \
    "GET" \
    "/api/staking/get_staking_stats.php" \
    "" \
    "200"

test_endpoint \
    "Obtener estadísticas con usuario" \
    "GET" \
    "/api/staking/get_staking_stats.php?user_id=$USER_ID" \
    "" \
    "200"

echo "4. Testing POST /api/staking/stake_tokens.php"
echo "--------------------------------------"
test_endpoint \
    "Stake tokens (datos válidos)" \
    "POST" \
    "/api/staking/stake_tokens.php" \
    "{\"user_id\":$USER_ID,\"amount\":\"100.5\",\"pool_id\":0,\"tx_hash\":\"${TX_HASH_PREFIX}01\"}" \
    "201"

test_endpoint \
    "Stake tokens (sin tx_hash)" \
    "POST" \
    "/api/staking/stake_tokens.php" \
    "{\"user_id\":$USER_ID,\"amount\":\"100.5\",\"pool_id\":0}" \
    "400"

test_endpoint \
    "Stake tokens (monto inválido)" \
    "POST" \
    "/api/staking/stake_tokens.php" \
    "{\"user_id\":$USER_ID,\"amount\":\"-10\",\"pool_id\":0,\"tx_hash\":\"${TX_HASH_PREFIX}02\"}" \
    "500"

test_endpoint \
    "Stake tokens (pool inválido)" \
    "POST" \
    "/api/staking/stake_tokens.php" \
    "{\"user_id\":$USER_ID,\"amount\":\"100\",\"pool_id\":99,\"tx_hash\":\"${TX_HASH_PREFIX}03\"}" \
    "500"

echo "5. Testing POST /api/staking/claim_rewards.php"
echo "--------------------------------------"
test_endpoint \
    "Claim rewards (datos válidos)" \
    "POST" \
    "/api/staking/claim_rewards.php" \
    "{\"user_id\":$USER_ID,\"amount\":\"5.25\",\"tx_hash\":\"${TX_HASH_PREFIX}04\"}" \
    "200"

test_endpoint \
    "Claim rewards (sin amount)" \
    "POST" \
    "/api/staking/claim_rewards.php" \
    "{\"user_id\":$USER_ID,\"tx_hash\":\"${TX_HASH_PREFIX}05\"}" \
    "400"

test_endpoint \
    "Claim rewards (tx duplicado)" \
    "POST" \
    "/api/staking/claim_rewards.php" \
    "{\"user_id\":$USER_ID,\"amount\":\"5.25\",\"tx_hash\":\"${TX_HASH_PREFIX}04\"}" \
    "500"

echo "6. Testing POST /api/staking/unstake_tokens.php"
echo "--------------------------------------"
test_endpoint \
    "Unstake tokens (datos válidos)" \
    "POST" \
    "/api/staking/unstake_tokens.php" \
    "{\"user_id\":$USER_ID,\"amount\":\"50\",\"rewards\":\"2.5\",\"tx_hash\":\"${TX_HASH_PREFIX}06\"}" \
    "200"

test_endpoint \
    "Unstake tokens (sin rewards)" \
    "POST" \
    "/api/staking/unstake_tokens.php" \
    "{\"user_id\":$USER_ID,\"amount\":\"25\",\"tx_hash\":\"${TX_HASH_PREFIX}07\"}" \
    "200"

echo ""
echo "======================================"
echo "   RESUMEN DE TESTS"
echo "======================================"
echo -e "${GREEN}Tests Passed: $PASSED${NC}"
echo -e "${RED}Tests Failed: $FAILED${NC}"
echo "Total Tests: $((PASSED + FAILED))"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ Todos los tests pasaron!${NC}"
    exit 0
else
    echo -e "${RED}✗ Algunos tests fallaron${NC}"
    exit 1
fi
