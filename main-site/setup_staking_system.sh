#!/bin/bash

# ============================================
# SPHORIA - MEMBERSHIP STAKING SYSTEM SETUP
# ============================================
# Script de instalación automática
# Uso: bash setup_staking_system.sh

set -e  # Exit on error

echo "============================================"
echo "  SPHORIA MEMBERSHIP STAKING SYSTEM"
echo "  Setup Automático"
echo "============================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Verificar que estamos en el directorio correcto
echo -e "${YELLOW}[1/8]${NC} Verificando ubicación..."
if [ ! -f "STAKING_DEPLOYMENT_GUIDE.md" ]; then
    echo -e "${RED}Error: Debes ejecutar este script desde /var/www/html${NC}"
    exit 1
fi
echo -e "${GREEN}✓${NC} Directorio correcto"
echo ""

# Step 2: Verificar base de datos
echo -e "${YELLOW}[2/8]${NC} Verificando tablas de base de datos..."
DB_PASS=$(grep DB_PASS .env | cut -d '=' -f2)
DB_USER=$(grep DB_USER .env | cut -d '=' -f2)
DB_NAME=$(grep DB_NAME .env | cut -d '=' -f2)

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE membership_stakes;" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Tabla membership_stakes existe"
else
    echo -e "${RED}✗${NC} Tabla membership_stakes NO existe"
    echo "Creando tabla..."
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/MAIN.sql
    echo -e "${GREEN}✓${NC} Tabla creada"
fi

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE membership_transactions;" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Tabla membership_transactions existe"
else
    echo -e "${RED}✗${NC} ERROR: Tabla membership_transactions NO existe. Ejecuta MAIN.sql primero."
    exit 1
fi
echo ""

# Step 3: Verificar APIs
echo -e "${YELLOW}[3/8]${NC} Verificando APIs backend..."
if [ -f "api/membership/purchase_with_stake.php" ]; then
    echo -e "${GREEN}✓${NC} purchase_with_stake.php existe"
else
    echo -e "${RED}✗${NC} purchase_with_stake.php NO existe"
    exit 1
fi

if [ -f "api/membership/unstake.php" ]; then
    echo -e "${GREEN}✓${NC} unstake.php existe"
else
    echo -e "${RED}✗${NC} unstake.php NO existe"
    exit 1
fi

if [ -f "api/membership/get_stakes.php" ]; then
    echo -e "${GREEN}✓${NC} get_stakes.php existe"
else
    echo -e "${RED}✗${NC} get_stakes.php NO existe"
    exit 1
fi
echo ""

# Step 4: Verificar smart contract
echo -e "${YELLOW}[4/8]${NC} Verificando smart contract..."
if [ -f "escrow-system/contracts/MembershipStaking.sol" ]; then
    echo -e "${GREEN}✓${NC} MembershipStaking.sol existe"
else
    echo -e "${RED}✗${NC} MembershipStaking.sol NO existe"
    exit 1
fi
echo ""

# Step 5: Verificar JavaScript nuevo
echo -e "${YELLOW}[5/8]${NC} Verificando nuevo JavaScript..."
if [ -f "membership/membership-new.js" ]; then
    echo -e "${GREEN}✓${NC} membership-new.js existe"
else
    echo -e "${RED}✗${NC} membership-new.js NO existe"
    exit 1
fi
echo ""

# Step 6: Hacer backup de membership.php
echo -e "${YELLOW}[6/8]${NC} Haciendo backup de membership.php..."
if [ ! -f "membership/membership.php.backup" ]; then
    cp membership/membership.php membership/membership.php.backup
    echo -e "${GREEN}✓${NC} Backup creado: membership.php.backup"
else
    echo -e "${YELLOW}⚠${NC} Backup ya existe, saltando..."
fi
echo ""

# Step 7: Mostrar instrucciones para deploy del contrato
echo -e "${YELLOW}[7/8]${NC} Instrucciones para desplegar smart contract..."
echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}IMPORTANTE: DESPLIEGUE DEL SMART CONTRACT${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Necesitas desplegar el smart contract manualmente:"
echo ""
echo "1. Ir al directorio de Hardhat:"
echo "   cd escrow-system"
echo ""
echo "2. Crear archivo .env con:"
echo "   PRIVATE_KEY=tu_private_key_del_deployer"
echo "   ALCHEMY_API_KEY=tu_alchemy_api_key"
echo ""
echo "3. Compilar:"
echo "   npx hardhat compile"
echo ""
echo "4. Desplegar a Polygon:"
echo "   npx hardhat run scripts/deploy_membership_staking.js --network polygon"
echo ""
echo "5. Copiar la dirección del contrato desplegado"
echo ""
echo "6. Actualizar .env principal:"
echo "   nano /var/www/html/.env"
echo "   Agregar: MEMBERSHIP_STAKING_CONTRACT=0xTU_DIRECCION_AQUI"
echo ""
echo "7. Actualizar membership-new.js línea 7:"
echo "   const MEMBERSHIP_STAKING_CONTRACT = '0xTU_DIRECCION_AQUI';"
echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

read -p "¿Has desplegado el contrato y tienes la dirección? (s/n): " deployed

if [ "$deployed" != "s" ]; then
    echo ""
    echo -e "${YELLOW}⚠${NC} Setup pausado. Ejecuta nuevamente cuando tengas la dirección del contrato."
    echo ""
    echo "Para continuar después:"
    echo "  1. Despliega el contrato"
    echo "  2. Ejecuta: bash setup_staking_system.sh"
    exit 0
fi

# Step 8: Solicitar dirección del contrato
echo ""
read -p "Ingresa la dirección del contrato desplegado (0x...): " contract_address

# Validar formato de dirección
if [[ ! $contract_address =~ ^0x[a-fA-F0-9]{40}$ ]]; then
    echo -e "${RED}✗${NC} Dirección inválida. Debe ser formato 0x... (42 caracteres)"
    exit 1
fi

echo ""
echo -e "${GREEN}✓${NC} Dirección válida: $contract_address"
echo ""

# Actualizar .env
echo -e "${YELLOW}[8/8]${NC} Actualizando configuración..."
if grep -q "MEMBERSHIP_STAKING_CONTRACT" .env; then
    sed -i "s/MEMBERSHIP_STAKING_CONTRACT=.*/MEMBERSHIP_STAKING_CONTRACT=$contract_address/" .env
    echo -e "${GREEN}✓${NC} .env actualizado"
else
    echo "" >> .env
    echo "# Membership Staking Contract" >> .env
    echo "MEMBERSHIP_STAKING_CONTRACT=$contract_address" >> .env
    echo -e "${GREEN}✓${NC} .env actualizado"
fi

# Actualizar membership-new.js
sed -i "s/CONTRACT_ADDRESS_PLACEHOLDER/$contract_address/" membership/membership-new.js
echo -e "${GREEN}✓${NC} membership-new.js actualizado"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ SETUP COMPLETADO EXITOSAMENTE${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Siguiente paso MANUAL:"
echo ""
echo "Reemplazar el JavaScript en membership.php:"
echo "  1. Abre: nano membership/membership.php"
echo "  2. Busca la línea 235 (<script>)"
echo "  3. Reemplaza TODO el <script>...</script> con el contenido de:"
echo "     membership/membership-new.js"
echo ""
echo "O copia el contenido automáticamente:"
echo "  # Ver el nuevo script:"
echo "  cat membership/membership-new.js"
echo ""
echo "Luego prueba la compra de membresía en:"
echo "  http://45.61.148.251/membership/membership.php"
echo ""
echo -e "${YELLOW}⚠ IMPORTANTE:${NC} Asegúrate de estar en Polygon Mainnet en MetaMask"
echo ""
echo "Documentación completa: STAKING_DEPLOYMENT_GUIDE.md"
echo ""
