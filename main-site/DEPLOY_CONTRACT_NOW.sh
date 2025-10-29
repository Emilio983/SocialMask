#!/bin/bash

# ============================================
# DEPLOY MEMBERSHIP STAKING CONTRACT
# ============================================
# Script para desplegar el contrato de staking
# Ejecuta esto AHORA para completar el sistema

set -e

echo ""
echo "════════════════════════════════════════════"
echo "  DEPLOY MEMBERSHIP STAKING CONTRACT"
echo "════════════════════════════════════════════"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Verificar que estamos en el directorio correcto
if [ ! -d "escrow-system" ]; then
    echo -e "${RED}Error: Debes ejecutar este script desde /var/www/html${NC}"
    exit 1
fi

cd escrow-system

echo -e "${YELLOW}[1/5]${NC} Verificando configuración..."
echo ""

# Verificar que el contrato compiló
if [ ! -d "artifacts/contracts/MembershipStaking.sol" ]; then
    echo -e "${YELLOW}⚠${NC} Compilando contratos..."
    npx hardhat compile
    echo -e "${GREEN}✓${NC} Contratos compilados"
else
    echo -e "${GREEN}✓${NC} Contratos ya compilados"
fi

echo ""

# Verificar .env
if [ ! -f ".env" ]; then
    echo -e "${RED}✗${NC} Archivo .env NO existe"
    echo ""
    echo "Creando .env..."
    cat > .env << 'EOF'
# Blockchain Configuration
SPHE_TOKEN_ADDRESS=0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b
TREASURY_WALLET=0xa1052872c755B5B2192b54ABD5F08546eeE6aa20

# Infura Configuration
INFURA_API_KEY=f210fc05834a4070871dbc89b2774608

# IMPORTANTE: Agregar tu PRIVATE_KEY para deployment
# Esta es la private key de la wallet que desplegará el contrato
# DEBE tener MATIC para gas fees (~0.01 MATIC)
PRIVATE_KEY=

# PolygonScan API Key (para verificación de contratos - opcional)
POLYGONSCAN_API_KEY=

# Network (polygon, amoy, localhost)
NETWORK=polygon
EOF
    echo -e "${GREEN}✓${NC} Archivo .env creado"
fi

# Verificar PRIVATE_KEY
PRIVATE_KEY=$(grep PRIVATE_KEY .env | cut -d '=' -f2)

if [ -z "$PRIVATE_KEY" ] || [ "$PRIVATE_KEY" == "" ]; then
    echo -e "${RED}✗${NC} PRIVATE_KEY no configurada"
    echo ""
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}ACCIÓN REQUERIDA:${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "1. Abre Metamask"
    echo "2. Click en los 3 puntos → Account details → Export Private Key"
    echo "3. Ingresa tu contraseña"
    echo "4. Copia la private key"
    echo "5. Edita el archivo .env:"
    echo "   nano .env"
    echo "6. Pega tu private key en la línea:"
    echo "   PRIVATE_KEY=tu_private_key_aqui_sin_0x"
    echo "7. Guarda (Ctrl+O, Enter, Ctrl+X)"
    echo "8. Ejecuta nuevamente este script"
    echo ""
    echo -e "${YELLOW}⚠ IMPORTANTE:${NC} Asegúrate de tener al menos 0.01 MATIC"
    echo "   en esa wallet para pagar el gas del deployment"
    echo ""
    exit 1
else
    echo -e "${GREEN}✓${NC} PRIVATE_KEY configurada"
fi

echo ""
echo -e "${YELLOW}[2/5]${NC} Verificando balance de MATIC..."

# Verificar balance (esto fallará si no hay balance, pero es OK)
echo ""
echo "Ejecutando verificación..."
echo ""

npx hardhat run --network polygon - <<'VERIFY_SCRIPT'
const hre = require("hardhat");

async function main() {
  const [deployer] = await hre.ethers.getSigners();
  const balance = await hre.ethers.provider.getBalance(deployer.address);

  console.log("Deployer:", deployer.address);
  console.log("Balance:", hre.ethers.formatEther(balance), "MATIC");

  const minBalance = hre.ethers.parseEther("0.01");
  if (balance < minBalance) {
    console.error("\n⚠ ADVERTENCIA: Balance bajo. Necesitas al menos 0.01 MATIC");
    console.error("   Envía MATIC a:", deployer.address);
    process.exit(1);
  }

  console.log("✓ Balance suficiente para deployment");
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
VERIFY_SCRIPT

if [ $? -ne 0 ]; then
    echo ""
    echo -e "${RED}✗${NC} No hay suficiente MATIC para el deployment"
    echo ""
    echo "Envía al menos 0.01 MATIC a la wallet del deployer"
    echo "Puedes obtener MATIC en:"
    echo "  - Binance, Coinbase, etc."
    echo "  - Bridge desde Ethereum"
    exit 1
fi

echo ""
echo -e "${YELLOW}[3/5]${NC} Desplegando MembershipStaking a Polygon Mainnet..."
echo ""
echo -e "${BLUE}⏳ Esto puede tomar 1-2 minutos...${NC}"
echo ""

# Deploy
npx hardhat run scripts/deploy_membership_staking.js --network polygon

if [ $? -ne 0 ]; then
    echo ""
    echo -e "${RED}✗${NC} Error al desplegar el contrato"
    exit 1
fi

echo ""
echo -e "${GREEN}✓${NC} Contrato desplegado exitosamente"
echo ""

# Leer la dirección del contrato del archivo de deployment
DEPLOYMENT_FILE=$(ls -t deployments/membership-staking-*.json 2>/dev/null | head -1)

if [ -f "$DEPLOYMENT_FILE" ]; then
    CONTRACT_ADDRESS=$(grep -o '"contractAddress":"0x[a-fA-F0-9]*"' "$DEPLOYMENT_FILE" | cut -d'"' -f4)

    if [ -n "$CONTRACT_ADDRESS" ]; then
        echo -e "${YELLOW}[4/5]${NC} Actualizando configuración..."
        echo ""

        # Actualizar .env principal
        cd ..

        if grep -q "MEMBERSHIP_STAKING_CONTRACT" .env; then
            sed -i "s/MEMBERSHIP_STAKING_CONTRACT=.*/MEMBERSHIP_STAKING_CONTRACT=$CONTRACT_ADDRESS/" .env
        else
            echo "" >> .env
            echo "# Membership Staking Contract" >> .env
            echo "MEMBERSHIP_STAKING_CONTRACT=$CONTRACT_ADDRESS" >> .env
        fi

        echo -e "${GREEN}✓${NC} .env actualizado"

        # Actualizar membership-new.js
        sed -i "s/CONTRACT_ADDRESS_PLACEHOLDER/$CONTRACT_ADDRESS/" membership/membership-new.js
        sed -i "s/'0x'/'$CONTRACT_ADDRESS'/" membership/membership-new.js

        echo -e "${GREEN}✓${NC} membership-new.js actualizado"

        echo ""
        echo -e "${YELLOW}[5/5]${NC} Deployment completo"
        echo ""
        echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo -e "${GREEN}✅ DEPLOYMENT EXITOSO${NC}"
        echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo ""
        echo "Dirección del contrato:"
        echo -e "${BLUE}$CONTRACT_ADDRESS${NC}"
        echo ""
        echo "Siguiente paso:"
        echo "  bash integrate_membership_staking.sh"
        echo ""
    fi
fi
