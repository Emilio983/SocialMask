#!/bin/bash

# Test de APIs de PayPerView
# Este script prueba todas las APIs del sistema de paywall

echo "🧪 TESTING PAYWALL APIs"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Configuración
BASE_URL="http://localhost"
API_URL="$BASE_URL/api/paywall"
JWT_TOKEN=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Función para hacer requests
api_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    local auth=$4
    
    if [ "$auth" = "true" ] && [ -n "$JWT_TOKEN" ]; then
        curl -s -X $method \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $JWT_TOKEN" \
            -d "$data" \
            "$API_URL/$endpoint"
    else
        curl -s -X $method \
            -H "Content-Type: application/json" \
            -d "$data" \
            "$API_URL/$endpoint"
    fi
}

# Función para mostrar resultado
show_result() {
    local test_name=$1
    local response=$2
    local expected=$3
    
    if echo "$response" | grep -q "$expected"; then
        echo -e "${GREEN}✅ $test_name${NC}"
    else
        echo -e "${RED}❌ $test_name${NC}"
        echo "   Response: $response"
    fi
}

# ============================================
# 1. LOGIN (obtener JWT)
# ============================================
echo -e "${BLUE}1️⃣  Obteniendo JWT Token...${NC}"

read -p "Username: " username
read -sp "Password: " password
echo ""

login_response=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"$username\",\"password\":\"$password\"}" \
    "$BASE_URL/api/auth/login.php")

JWT_TOKEN=$(echo $login_response | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -n "$JWT_TOKEN" ]; then
    echo -e "${GREEN}✅ Login successful${NC}"
    echo "Token: ${JWT_TOKEN:0:20}..."
else
    echo -e "${RED}❌ Login failed${NC}"
    echo "Response: $login_response"
    exit 1
fi

echo ""

# ============================================
# 2. CREATE CONTENT
# ============================================
echo -e "${BLUE}2️⃣  Testing CREATE CONTENT${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

create_data='{
    "contract_content_id": 999,
    "title": "Test Content",
    "description": "This is a test content",
    "price": "10.000000000000000000",
    "content_type": "article",
    "preview_text": "This is the preview..."
}'

response=$(api_request "POST" "create_content.php" "$create_data" "true")
show_result "Create content" "$response" "Content created successfully"

# Extraer content_id
CONTENT_ID=$(echo $response | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
echo "   Content ID: $CONTENT_ID"

echo ""

# ============================================
# 3. GET CONTENT
# ============================================
echo -e "${BLUE}3️⃣  Testing GET CONTENT${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Sin autenticación (solo preview)
response=$(curl -s "$API_URL/get_content.php?id=$CONTENT_ID")
show_result "Get content (no auth)" "$response" "has_access.*false"

# Con autenticación (creador tiene acceso)
response=$(curl -s -H "Authorization: Bearer $JWT_TOKEN" \
    "$API_URL/get_content.php?id=$CONTENT_ID")
show_result "Get content (with auth)" "$response" "has_access.*true"

echo ""

# ============================================
# 4. CHECK ACCESS
# ============================================
echo -e "${BLUE}4️⃣  Testing CHECK ACCESS${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

response=$(curl -s -H "Authorization: Bearer $JWT_TOKEN" \
    "$API_URL/check_access.php?content_id=$CONTENT_ID")
show_result "Check access" "$response" "has_access"

echo ""

# ============================================
# 5. RECORD PURCHASE
# ============================================
echo -e "${BLUE}5️⃣  Testing RECORD PURCHASE${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Generar tx_hash fake
TX_HASH="0x$(openssl rand -hex 32)"

purchase_data="{
    \"content_id\": $CONTENT_ID,
    \"tx_hash\": \"$TX_HASH\",
    \"gelato_task_id\": \"test_task_123\"
}"

response=$(api_request "POST" "record_purchase.php" "$purchase_data" "true")

# Nota: Fallará porque el usuario es el creador
if echo "$response" | grep -q "cannot purchase their own"; then
    echo -e "${GREEN}✅ Record purchase (validation works)${NC}"
else
    show_result "Record purchase" "$response" "Purchase recorded"
fi

echo ""

# ============================================
# 6. LIST CONTENT
# ============================================
echo -e "${BLUE}6️⃣  Testing LIST CONTENT${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

response=$(curl -s "$API_URL/list_content.php?limit=10")
show_result "List content" "$response" "content"

# Con filtros
response=$(curl -s "$API_URL/list_content.php?content_type=article&sort_by=price")
show_result "List content (filtered)" "$response" "content"

echo ""

# ============================================
# 7. GET EARNINGS
# ============================================
echo -e "${BLUE}7️⃣  Testing GET EARNINGS${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

response=$(curl -s -H "Authorization: Bearer $JWT_TOKEN" \
    "$API_URL/get_earnings.php")
show_result "Get earnings" "$response" "stats"

echo ""

# ============================================
# 8. MY PURCHASES
# ============================================
echo -e "${BLUE}8️⃣  Testing MY PURCHASES${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

response=$(curl -s -H "Authorization: Bearer $JWT_TOKEN" \
    "$API_URL/my_purchases.php")
show_result "My purchases" "$response" "purchases"

echo ""

# ============================================
# RESUMEN
# ============================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${GREEN}✅ TESTS COMPLETADOS${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 APIs Testeadas:"
echo "   1. Create Content"
echo "   2. Get Content"
echo "   3. Check Access"
echo "   4. Record Purchase"
echo "   5. List Content"
echo "   6. Get Earnings"
echo "   7. My Purchases"
echo ""
echo "💡 Para testing completo:"
echo "   - Usar Postman collection"
echo "   - Probar con múltiples usuarios"
echo "   - Verificar transacciones reales"
echo ""
