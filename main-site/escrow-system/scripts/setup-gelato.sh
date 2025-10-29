#!/bin/bash

# Setup Gelato Relay para Sphoria Pay-Per-View
# Este script configura Gelato Relay para gasless transactions

echo "🚀 GELATO RELAY SETUP"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para preguntar
ask() {
    local prompt="$1"
    local default="$2"
    local result
    
    if [ -n "$default" ]; then
        read -p "$prompt [$default]: " result
        echo "${result:-$default}"
    else
        read -p "$prompt: " result
        echo "$result"
    fi
}

echo -e "${BLUE}📋 PASO 1: Información de Gelato${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Para usar Gelato Relay necesitas:"
echo "1. Crear cuenta en: https://app.gelato.network/"
echo "2. Obtener API Key"
echo "3. Depositar fondos (mínimo \$10 USD)"
echo ""
echo -e "${YELLOW}⚠️  ¿Ya tienes cuenta en Gelato Network? (y/n)${NC}"
read -r has_account

if [ "$has_account" != "y" ]; then
    echo ""
    echo -e "${RED}❌ Primero crea tu cuenta:${NC}"
    echo "   1. Ve a: https://app.gelato.network/"
    echo "   2. Conecta tu wallet"
    echo "   3. Obtén tu API Key"
    echo "   4. Regresa y ejecuta este script nuevamente"
    echo ""
    exit 1
fi

echo ""
echo -e "${BLUE}📋 PASO 2: Configuración${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Leer .env existente
if [ -f .env ]; then
    echo "✅ Archivo .env encontrado"
    source .env
else
    echo "⚠️  No se encontró .env, se creará uno nuevo"
fi

# API Key
echo ""
echo "🔑 Gelato API Key"
echo "Puedes obtenerla en: https://app.gelato.network/api-keys"
GELATO_API_KEY=$(ask "Ingresa tu Gelato API Key" "$GELATO_RELAY_API_KEY")

if [ -z "$GELATO_API_KEY" ]; then
    echo -e "${RED}❌ API Key es requerida${NC}"
    exit 1
fi

# Network
echo ""
echo "🌐 Network"
echo "1. Polygon Mainnet (producción)"
echo "2. Amoy Testnet (pruebas)"
NETWORK_CHOICE=$(ask "Selecciona network (1/2)" "2")

if [ "$NETWORK_CHOICE" = "1" ]; then
    NETWORK="polygon"
    CHAIN_ID="137"
    BUNDLER_RPC="https://api.gelato.digital/bundler/polygon"
else
    NETWORK="amoy"
    CHAIN_ID="80002"
    BUNDLER_RPC="https://api.gelato.digital/bundler/amoy"
fi

# Platform Wallet
echo ""
echo "💼 Platform Wallet"
echo "Esta es la wallet que recibirá los fees de la plataforma"
PLATFORM_WALLET=$(ask "Platform Wallet Address" "$PLATFORM_WALLET")

if [ -z "$PLATFORM_WALLET" ]; then
    echo -e "${RED}❌ Platform Wallet es requerida${NC}"
    exit 1
fi

# Actualizar .env
echo ""
echo -e "${BLUE}📋 PASO 3: Guardando configuración${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Crear backup
if [ -f .env ]; then
    cp .env .env.backup
    echo "💾 Backup creado: .env.backup"
fi

# Actualizar o crear .env
cat > .env.gelato << EOF
# Gelato Relay Configuration
GELATO_RELAY_API_KEY="${GELATO_API_KEY}"
GELATO_RELAY_URL="https://relay.gelato.digital"
ERC4337_BUNDLER_RPC_URL="${BUNDLER_RPC}"
GELATO_CHAIN_ID="${CHAIN_ID}"

# Platform Configuration
PLATFORM_WALLET="${PLATFORM_WALLET}"
NETWORK="${NETWORK}"
EOF

# Merge con .env existente
if [ -f .env ]; then
    # Remover líneas antiguas de Gelato
    grep -v "GELATO_" .env | grep -v "ERC4337_" > .env.tmp
    cat .env.tmp .env.gelato > .env
    rm .env.tmp .env.gelato
else
    mv .env.gelato .env
fi

echo "✅ Configuración guardada en .env"

# Actualizar frontend config
echo ""
echo -e "${BLUE}📋 PASO 4: Actualizando frontend${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

FRONTEND_CONFIG="../assets/js/config.js"

if [ -f "$FRONTEND_CONFIG" ]; then
    # Agregar o actualizar GELATO_RELAY_API_KEY
    if grep -q "GELATO_RELAY_API_KEY" "$FRONTEND_CONFIG"; then
        sed -i "s/const GELATO_RELAY_API_KEY = .*/const GELATO_RELAY_API_KEY = '${GELATO_API_KEY}';/" "$FRONTEND_CONFIG"
    else
        echo "const GELATO_RELAY_API_KEY = '${GELATO_API_KEY}';" >> "$FRONTEND_CONFIG"
    fi
    
    echo "✅ Frontend config actualizado"
else
    echo "⚠️  Frontend config no encontrado: $FRONTEND_CONFIG"
fi

# Verificar balance de Gelato
echo ""
echo -e "${BLUE}📋 PASO 5: Verificando balance${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

BALANCE_RESPONSE=$(curl -s -H "Authorization: Bearer ${GELATO_API_KEY}" \
    https://relay.gelato.digital/relays/v2/balance)

if echo "$BALANCE_RESPONSE" | grep -q "balance"; then
    echo "✅ Conexión con Gelato exitosa"
    echo ""
    echo "Balance actual:"
    echo "$BALANCE_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$BALANCE_RESPONSE"
else
    echo -e "${YELLOW}⚠️  No se pudo verificar balance${NC}"
    echo "Respuesta: $BALANCE_RESPONSE"
fi

echo ""
echo -e "${YELLOW}⚠️  IMPORTANTE: Debes depositar fondos en Gelato${NC}"
echo "   1. Ve a: https://app.gelato.network/balance"
echo "   2. Deposita mínimo \$10 USD"
echo "   3. Los fondos se usarán para pagar el gas de las transacciones gasless"
echo ""

# Testing
echo ""
echo -e "${BLUE}📋 PASO 6: Testing (opcional)${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "¿Quieres correr tests de Gelato Relay? (y/n)"
read -r run_tests

if [ "$run_tests" = "y" ]; then
    if [ -f "test-gelato.js" ]; then
        echo "🧪 Corriendo tests..."
        node test-gelato.js
    else
        echo "⚠️  test-gelato.js no encontrado"
    fi
fi

# Resumen
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${GREEN}✅ SETUP COMPLETADO${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📊 Configuración:"
echo "   Network:      ${NETWORK}"
echo "   Chain ID:     ${CHAIN_ID}"
echo "   API Key:      ${GELATO_API_KEY:0:10}..."
echo "   Platform:     ${PLATFORM_WALLET:0:10}..."
echo ""
echo "📋 Próximos pasos:"
echo "   1. ✅ Gelato configurado"
echo "   2. 💰 Deposita fondos en Gelato"
echo "   3. 🧪 Prueba gasless transactions"
echo "   4. 🚀 Deploy a producción"
echo ""
echo "🔗 Links útiles:"
echo "   Dashboard: https://app.gelato.network/"
echo "   Balance:   https://app.gelato.network/balance"
echo "   Tasks:     https://app.gelato.network/tasks"
echo "   Docs:      https://docs.gelato.network/"
echo ""
