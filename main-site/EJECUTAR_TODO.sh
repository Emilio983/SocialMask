#!/bin/bash

# ============================================
# SCRIPT COMPLETO - EJECUTA TODO AUTOMÁTICAMENTE
# ============================================
# Este script te guiará paso a paso para completar el deployment

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

clear

echo ""
echo -e "${CYAN}════════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}   🚀 DEPLOYMENT COMPLETO DEL SISTEMA DE STAKING           ${NC}"
echo -e "${CYAN}════════════════════════════════════════════════════════════${NC}"
echo ""

cd /var/www/html

# ============================================
# PASO 1: CONFIGURAR PRIVATE KEY
# ============================================

echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  PASO 1/3: CONFIGURAR PRIVATE KEY                        ${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Verificar si ya hay una private key
PRIVATE_KEY=$(grep "^PRIVATE_KEY=" escrow-system/.env | cut -d'=' -f2)

if [ -z "$PRIVATE_KEY" ] || [ "$PRIVATE_KEY" == "" ]; then
    echo -e "${RED}⚠  No se ha configurado una PRIVATE_KEY${NC}"
    echo ""
    echo -e "${CYAN}Para obtener tu PRIVATE KEY de MetaMask:${NC}"
    echo ""
    echo "  1. Abre MetaMask en tu navegador"
    echo "  2. Click en los 3 puntos (arriba derecha)"
    echo "  3. Click en 'Account details'"
    echo "  4. Click en 'Show private key'"
    echo "  5. Ingresa tu contraseña de MetaMask"
    echo "  6. Click en 'Confirm'"
    echo "  7. Click en 'Hold to reveal Private Key'"
    echo "  8. Copia la private key"
    echo ""
    echo -e "${YELLOW}⚠  IMPORTANTE:${NC}"
    echo "  • NO incluyas el prefijo '0x'"
    echo "  • Tu wallet DEBE tener al menos 0.01 MATIC para gas"
    echo "  • Esta private key se usará SOLO para deployment"
    echo ""
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""

    read -p "Pega tu PRIVATE KEY aquí (sin 0x): " USER_PRIVATE_KEY

    if [ -z "$USER_PRIVATE_KEY" ]; then
        echo ""
        echo -e "${RED}✗ No ingresaste ninguna private key${NC}"
        echo ""
        echo "Ejecuta este script nuevamente cuando tengas tu private key lista"
        exit 1
    fi

    # Guardar la private key en .env
    if grep -q "^PRIVATE_KEY=" escrow-system/.env; then
        sed -i "s/^PRIVATE_KEY=.*/PRIVATE_KEY=$USER_PRIVATE_KEY/" escrow-system/.env
    else
        echo "PRIVATE_KEY=$USER_PRIVATE_KEY" >> escrow-system/.env
    fi

    echo ""
    echo -e "${GREEN}✓ Private key guardada en .env${NC}"

else
    echo -e "${GREEN}✓ Private key ya está configurada${NC}"
    echo -e "${CYAN}  Primeros caracteres: ${PRIVATE_KEY:0:10}...${NC}"
fi

echo ""
echo -e "${CYAN}Presiona ENTER para continuar con el deployment...${NC}"
read

# ============================================
# PASO 2: DESPLEGAR SMART CONTRACT
# ============================================

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  PASO 2/3: DESPLEGAR SMART CONTRACT A POLYGON           ${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${BLUE}⏳ Ejecutando DEPLOY_CONTRACT_NOW.sh...${NC}"
echo ""

bash DEPLOY_CONTRACT_NOW.sh

if [ $? -ne 0 ]; then
    echo ""
    echo -e "${RED}✗ Error en el deployment${NC}"
    echo ""
    echo "Por favor revisa los errores arriba y:"
    echo "  1. Verifica que tu wallet tenga MATIC"
    echo "  2. Verifica que la private key sea correcta"
    echo "  3. Ejecuta este script nuevamente"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ Smart contract desplegado exitosamente${NC}"
echo ""
echo -e "${CYAN}Presiona ENTER para continuar con la integración...${NC}"
read

# ============================================
# PASO 3: INTEGRAR JAVASCRIPT
# ============================================

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  PASO 3/3: INTEGRAR JAVASCRIPT CON STAKING              ${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${BLUE}⏳ Ejecutando integrate_membership_staking.sh...${NC}"
echo ""

bash integrate_membership_staking.sh

if [ $? -ne 0 ]; then
    echo ""
    echo -e "${RED}✗ Error en la integración${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ JavaScript integrado exitosamente${NC}"

# ============================================
# FINALIZACIÓN
# ============================================

echo ""
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}           ✅ DEPLOYMENT COMPLETADO EXITOSAMENTE         ${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Obtener dirección del contrato
DEPLOYMENT_FILE=$(ls -t escrow-system/deployments/membership-staking-*.json 2>/dev/null | head -1)

if [ -f "$DEPLOYMENT_FILE" ]; then
    CONTRACT_ADDRESS=$(grep -o '"contractAddress":"0x[a-fA-F0-9]*"' "$DEPLOYMENT_FILE" | cut -d'"' -f4)

    if [ -n "$CONTRACT_ADDRESS" ]; then
        echo -e "${CYAN}📍 Dirección del Contrato:${NC}"
        echo -e "${BLUE}   $CONTRACT_ADDRESS${NC}"
        echo ""
        echo -e "${CYAN}🔗 Ver en PolygonScan:${NC}"
        echo -e "${BLUE}   https://polygonscan.com/address/$CONTRACT_ADDRESS${NC}"
        echo ""
    fi
fi

echo -e "${CYAN}✨ Sistema de Staking Activado:${NC}"
echo "   ✓ Pagos divididos 50/50 (pago + stake)"
echo "   ✓ Stake bloqueado por 30 días"
echo "   ✓ Smart contract en Polygon Mainnet"
echo "   ✓ Branding 'The Social Mask' actualizado"
echo ""

echo -e "${CYAN}🧪 Prueba tu sistema:${NC}"
echo -e "${BLUE}   http://45.61.148.251/membership/membership.php${NC}"
echo ""

echo -e "${CYAN}📊 Precios con staking:${NC}"
echo "   • Platinum: 100 SPHE (50 pago + 50 stake)"
echo "   • Gold:     250 SPHE (125 pago + 125 stake)"
echo "   • Diamond:  500 SPHE (250 pago + 250 stake)"
echo "   • Creator:  750 SPHE (375 pago + 375 stake)"
echo ""

echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}            🎉 TODO LISTO - SISTEMA FUNCIONAL            ${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
