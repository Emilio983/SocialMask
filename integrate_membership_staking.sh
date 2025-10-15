#!/bin/bash

# ============================================
# INTEGRATE MEMBERSHIP STAKING JAVASCRIPT
# ============================================
# Integra el nuevo JavaScript con staking en membership.php

set -e

echo ""
echo "════════════════════════════════════════════"
echo "  INTEGRAR SISTEMA DE STAKING"
echo "════════════════════════════════════════════"
echo ""

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

cd /var/www/html

echo -e "${YELLOW}[1/3]${NC} Haciendo backup de membership.php..."
cp membership/membership.php membership/membership.php.backup.$(date +%Y%m%d_%H%M%S)
echo -e "${GREEN}✓${NC} Backup creado"

echo ""
echo -e "${YELLOW}[2/3]${NC} Integrando nuevo JavaScript con staking..."

# Extraer todo ANTES del <script> original
sed -n '1,/^    <script>$/p' membership/membership.php > /tmp/membership_before.txt

# Extraer todo DESPUÉS del </script> original
sed -n '/<\/script>$/,$p' membership/membership.php | tail -n +2 > /tmp/membership_after.txt

# Juntar: antes + nuevo script + después
cat /tmp/membership_before.txt > membership/membership.php.new
echo "    <script>" >> membership/membership.php.new
sed -n '/^\/\/ ============================================/,/^$/p' membership/membership-new.js | sed '1d;$d' >> membership/membership.php.new
echo "    </script>" >> membership/membership.php.new
cat /tmp/membership_after.txt >> membership/membership.php.new

# Reemplazar
mv membership/membership.php.new membership/membership.php

echo -e "${GREEN}✓${NC} JavaScript integrado"

echo ""
echo -e "${YELLOW}[3/3]${NC} Verificando..."

if grep -q "MEMBERSHIP_STAKING_CONTRACT" membership/membership.php; then
    echo -e "${GREEN}✓${NC} Sistema de staking integrado correctamente"
else
    echo -e "${YELLOW}⚠${NC} Advertencia: No se detectó MEMBERSHIP_STAKING_CONTRACT"
fi

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ INTEGRACIÓN COMPLETA${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Prueba el sistema:"
echo "  http://45.61.148.251/membership/membership.php"
echo ""
echo "Si algo salió mal, restaura el backup:"
echo "  cp membership/membership.php.backup.* membership/membership.php"
echo ""
