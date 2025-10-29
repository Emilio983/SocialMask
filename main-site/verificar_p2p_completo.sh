#!/bin/bash

echo "=================================="
echo "VERIFICACIÓN COMPLETA SISTEMA P2P"
echo "=================================="
echo ""

# 1. Verificar que p2p-client.js existe y es accesible
echo "1. Verificando p2p-client.js..."
if [ -f "/var/www/html/assets/js/p2p-client.js" ]; then
    echo "   ✅ Archivo existe"
    SIZE=$(stat -f%z "/var/www/html/assets/js/p2p-client.js" 2>/dev/null || stat -c%s "/var/www/html/assets/js/p2p-client.js")
    echo "   📦 Tamaño: $SIZE bytes"
else
    echo "   ❌ Archivo NO encontrado"
fi

# 2. Verificar que scripts.php carga p2p-client.js
echo ""
echo "2. Verificando scripts.php..."
if grep -q "p2p-client.js" "/var/www/html/components/scripts.php"; then
    echo "   ✅ scripts.php carga p2p-client.js"
else
    echo "   ❌ scripts.php NO carga p2p-client.js"
fi

# 3. Verificar páginas que incluyen scripts.php
echo ""
echo "3. Verificando páginas con P2P..."
PAGES=("dashboard.php" "community_view.php" "communities.php" "profile.php" "messages.php")
for page in "${PAGES[@]}"; do
    if grep -q "scripts.php" "/var/www/html/pages/$page" 2>/dev/null; then
        echo "   ✅ $page tiene P2P"
    else
        echo "   ❌ $page NO tiene P2P"
    fi
done

# 4. Verificar que p2p-toggle existe
echo ""
echo "4. Verificando p2p-toggle.php..."
if [ -f "/var/www/html/components/p2p-toggle.php" ]; then
    echo "   ✅ p2p-toggle.php existe"
else
    echo "   ❌ p2p-toggle.php NO existe"
fi

# 5. Verificar que navbar incluye p2p-toggle
echo ""
echo "5. Verificando navbar..."
if grep -q "p2p-toggle.php" "/var/www/html/components/navbar.php"; then
    echo "   ✅ navbar incluye p2p-toggle"
else
    echo "   ❌ navbar NO incluye p2p-toggle"
fi

# 6. Verificar API endpoints
echo ""
echo "6. Verificando API endpoints P2P..."
API_ENDPOINTS=("api/p2p/save-public-key.php" "api/p2p/store-metadata.php" "api/p2p/get-metadata.php")
for endpoint in "${API_ENDPOINTS[@]}"; do
    if [ -f "/var/www/html/$endpoint" ]; then
        echo "   ✅ /$endpoint existe"
    else
        echo "   ❌ /$endpoint NO existe"
    fi
done

# 7. Verificar TEST_SISTEMA_P2P.html
echo ""
echo "7. Verificando TEST_SISTEMA_P2P.html..."
if [ -f "/var/www/html/TEST_SISTEMA_P2P.html" ]; then
    if grep -q "p2p-client.js" "/var/www/html/TEST_SISTEMA_P2P.html"; then
        echo "   ✅ TEST_SISTEMA_P2P.html carga p2p-client.js"
    else
        echo "   ❌ TEST_SISTEMA_P2P.html NO carga p2p-client.js"
    fi
else
    echo "   ❌ TEST_SISTEMA_P2P.html NO existe"
fi

# 8. Verificar accesibilidad HTTP
echo ""
echo "8. Verificando accesibilidad HTTP..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/assets/js/p2p-client.js)
if [ "$HTTP_CODE" = "200" ]; then
    echo "   ✅ p2p-client.js accesible (HTTP $HTTP_CODE)"
else
    echo "   ❌ p2p-client.js NO accesible (HTTP $HTTP_CODE)"
fi

echo ""
echo "=================================="
echo "VERIFICACIÓN COMPLETADA"
echo "=================================="
