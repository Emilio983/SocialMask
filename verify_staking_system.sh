#!/bin/bash

# ============================================
# VERIFICACIÓN DEL SISTEMA DE STAKING
# ============================================
# Script para verificar que todo está configurado correctamente
# Uso: bash verify_staking_system.sh

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo ""
echo "════════════════════════════════════════════"
echo "  VERIFICACIÓN DEL SISTEMA DE STAKING"
echo "════════════════════════════════════════════"
echo ""

# Variables
DB_PASS=$(grep DB_PASS .env | cut -d '=' -f2)
DB_USER=$(grep DB_USER .env | cut -d '=' -f2)
DB_NAME=$(grep DB_NAME .env | cut -d '=' -f2)
STAKING_CONTRACT=$(grep MEMBERSHIP_STAKING_CONTRACT .env | cut -d '=' -f2)

# Función para check
check() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $2"
    else
        echo -e "${RED}✗${NC} $2"
        return 1
    fi
}

# 1. Verificar archivos críticos
echo -e "${BLUE}[1/8]${NC} Verificando archivos del sistema..."
[ -f "escrow-system/contracts/MembershipStaking.sol" ]
check $? "Smart contract existe"

[ -f "api/membership/purchase_with_stake.php" ]
check $? "API purchase_with_stake.php existe"

[ -f "api/membership/unstake.php" ]
check $? "API unstake.php existe"

[ -f "api/membership/get_stakes.php" ]
check $? "API get_stakes.php existe"

[ -f "membership/membership-new.js" ]
check $? "JavaScript nuevo existe"

[ -f "membership/membership.php" ]
check $? "Página membership.php existe"

echo ""

# 2. Verificar base de datos
echo -e "${BLUE}[2/8]${NC} Verificando base de datos..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1" > /dev/null 2>&1
check $? "Conexión a base de datos"

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE membership_stakes" > /dev/null 2>&1
check $? "Tabla membership_stakes existe"

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE membership_transactions" > /dev/null 2>&1
check $? "Tabla membership_transactions existe"

# Verificar estructura de membership_stakes
FIELDS=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE membership_stakes" 2>/dev/null | grep -c "staked_amount\|unlock_date\|claimed\|blockchain_stake_tx")
if [ "$FIELDS" -eq 4 ]; then
    echo -e "${GREEN}✓${NC} Tabla membership_stakes tiene todos los campos necesarios"
else
    echo -e "${RED}✗${NC} Tabla membership_stakes le faltan campos"
fi

echo ""

# 3. Verificar configuración .env
echo -e "${BLUE}[3/8]${NC} Verificando configuración (.env)..."
grep -q "SPHE_CONTRACT_ADDRESS" .env
check $? "SPHE_CONTRACT_ADDRESS definido"

if [ -n "$STAKING_CONTRACT" ] && [ "$STAKING_CONTRACT" != "" ]; then
    if [[ $STAKING_CONTRACT =~ ^0x[a-fA-F0-9]{40}$ ]]; then
        echo -e "${GREEN}✓${NC} MEMBERSHIP_STAKING_CONTRACT configurado: $STAKING_CONTRACT"
    else
        echo -e "${RED}✗${NC} MEMBERSHIP_STAKING_CONTRACT tiene formato inválido"
    fi
else
    echo -e "${YELLOW}⚠${NC} MEMBERSHIP_STAKING_CONTRACT NO configurado (esperado si no has desplegado)"
fi

echo ""

# 4. Verificar JavaScript
echo -e "${BLUE}[4/8]${NC} Verificando JavaScript..."
if grep -q "MEMBERSHIP_STAKING_CONTRACT" membership/membership-new.js > /dev/null 2>&1; then
    CONTRACT_IN_JS=$(grep "const MEMBERSHIP_STAKING_CONTRACT" membership/membership-new.js | grep -o "0x[a-fA-F0-9]*" | head -1)

    if [ -n "$CONTRACT_IN_JS" ] && [ "$CONTRACT_IN_JS" != "0x" ]; then
        if [[ $CONTRACT_IN_JS =~ ^0x[a-fA-F0-9]{40}$ ]]; then
            echo -e "${GREEN}✓${NC} JavaScript tiene dirección del contrato: $CONTRACT_IN_JS"
        else
            echo -e "${YELLOW}⚠${NC} Dirección en JavaScript parece incompleta"
        fi
    else
        echo -e "${YELLOW}⚠${NC} JavaScript aún tiene PLACEHOLDER (esperado antes de deploy)"
    fi
else
    echo -e "${RED}✗${NC} No se encuentra MEMBERSHIP_STAKING_CONTRACT en JS"
fi

# Verificar que membership.php usa el nuevo sistema
if grep -q "purchaseMembership" membership/membership.php > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} membership.php usa nuevo sistema de staking"
else
    echo -e "${YELLOW}⚠${NC} membership.php aún usa sistema antiguo (necesita actualización)"
fi

echo ""

# 5. Verificar permisos
echo -e "${BLUE}[5/8]${NC} Verificando permisos de archivos..."
[ -r "api/membership/purchase_with_stake.php" ]
check $? "purchase_with_stake.php es legible"

[ -r "api/membership/unstake.php" ]
check $? "unstake.php es legible"

[ -r "api/membership/get_stakes.php" ]
check $? "get_stakes.php es legible"

echo ""

# 6. Verificar sintaxis PHP
echo -e "${BLUE}[6/8]${NC} Verificando sintaxis de APIs..."
php -l api/membership/purchase_with_stake.php > /dev/null 2>&1
check $? "purchase_with_stake.php sin errores de sintaxis"

php -l api/membership/unstake.php > /dev/null 2>&1
check $? "unstake.php sin errores de sintaxis"

php -l api/membership/get_stakes.php > /dev/null 2>&1
check $? "get_stakes.php sin errores de sintaxis"

echo ""

# 7. Verificar Hardhat (opcional)
echo -e "${BLUE}[7/8]${NC} Verificando entorno de Hardhat..."
if [ -d "escrow-system/node_modules" ]; then
    echo -e "${GREEN}✓${NC} node_modules instalado"
else
    echo -e "${YELLOW}⚠${NC} node_modules no encontrado (ejecuta: cd escrow-system && npm install)"
fi

if [ -f "escrow-system/.env" ]; then
    echo -e "${GREEN}✓${NC} escrow-system/.env existe"

    if grep -q "PRIVATE_KEY" escrow-system/.env; then
        echo -e "${GREEN}✓${NC} PRIVATE_KEY configurada"
    else
        echo -e "${YELLOW}⚠${NC} PRIVATE_KEY no configurada"
    fi
else
    echo -e "${YELLOW}⚠${NC} escrow-system/.env no existe (necesario para deploy)"
fi

echo ""

# 8. Estadísticas actuales
echo -e "${BLUE}[8/8]${NC} Estadísticas actuales..."
STAKES_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM membership_stakes" 2>/dev/null)
TRANS_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT COUNT(*) FROM membership_transactions" 2>/dev/null)

echo -e "   Stakes registrados: ${GREEN}$STAKES_COUNT${NC}"
echo -e "   Transacciones: ${GREEN}$TRANS_COUNT${NC}"

if [ "$STAKES_COUNT" -gt 0 ]; then
    echo ""
    echo -e "${YELLOW}   Últimos stakes:${NC}"
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
    SELECT
        u.username,
        ms.plan_type,
        ms.staked_amount,
        ms.unlock_date,
        CASE WHEN ms.claimed = 1 THEN 'Reclamado' ELSE 'Activo' END as estado
    FROM membership_stakes ms
    JOIN users u ON ms.user_id = u.user_id
    ORDER BY ms.created_at DESC
    LIMIT 3
    " 2>/dev/null
fi

echo ""
echo "════════════════════════════════════════════"
echo ""

# Resumen final
WARNINGS=0
ERRORS=0

if [ -z "$STAKING_CONTRACT" ] || [ "$STAKING_CONTRACT" == "" ]; then
    ((WARNINGS++))
fi

if ! grep -q "purchaseMembership" membership/membership.php > /dev/null 2>&1; then
    ((WARNINGS++))
fi

if [ ! -f "escrow-system/.env" ]; then
    ((WARNINGS++))
fi

echo -e "${BLUE}RESUMEN:${NC}"
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓ Sistema completamente configurado y listo${NC}"
    echo ""
    echo "Siguiente paso: Probar compra de membresía"
    echo "URL: http://45.61.148.251/membership/membership.php"
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠ Sistema funcional con $WARNINGS advertencia(s)${NC}"
    echo ""
    echo "Advertencias típicas antes de deploy:"
    echo "  - MEMBERSHIP_STAKING_CONTRACT no configurado"
    echo "  - membership.php no actualizado"
    echo "  - escrow-system/.env no existe"
    echo ""
    echo "Siguiente paso: Desplegar smart contract"
    echo "Comando: cd escrow-system && npx hardhat run scripts/deploy_membership_staking.js --network polygon"
else
    echo -e "${RED}✗ Encontrados $ERRORS error(es)${NC}"
    echo ""
    echo "Revisa los errores arriba y corrige antes de continuar"
fi

echo ""
echo "Documentación completa: STAKING_DEPLOYMENT_GUIDE.md"
echo "Quick start: QUICK_START_STAKING.md"
echo ""
