#!/bin/bash

echo "========================================"
echo "VERIFICACIÓN SISTEMA DE DISPOSITIVOS"
echo "========================================"
echo ""

# Verificar páginas
echo "1. Verificando páginas web..."
if [ -f "/var/www/html/pages/devices/manage.php" ]; then
    echo "   ✅ manage.php (Panel de gestión)"
else
    echo "   ❌ manage.php NO encontrado"
fi

if [ -f "/var/www/html/pages/devices/link.php" ]; then
    echo "   ✅ link.php (Vinculación)"
else
    echo "   ❌ link.php NO encontrado"
fi

# Verificar API
echo ""
echo "2. Verificando API endpoints..."
for endpoint in generate-link-code verify-link-code check-code-status cancel-link-code; do
    if [ -f "/var/www/html/api/devices/${endpoint}.php" ]; then
        echo "   ✅ ${endpoint}.php"
    else
        echo "   ❌ ${endpoint}.php NO encontrado"
    fi
done

# Verificar JavaScript
echo ""
echo "3. Verificando JavaScript..."
if [ -f "/var/www/html/assets/js/device-linking.js" ]; then
    SIZE=$(stat -c%s "/var/www/html/assets/js/device-linking.js")
    echo "   ✅ device-linking.js ($SIZE bytes)"
else
    echo "   ❌ device-linking.js NO encontrado"
fi

# Verificar tablas
echo ""
echo "4. Verificando tablas de base de datos..."
mysql -u root thesocialmask -e "SHOW TABLES;" 2>/dev/null | grep -E "(device_link_codes|authorized_devices|security_logs|device_link_attempts)" | while read table; do
    COUNT=$(mysql -u root thesocialmask -e "SELECT COUNT(*) FROM $table;" 2>/dev/null | tail -1)
    echo "   ✅ $table ($COUNT registros)"
done

# Verificar documentación
echo ""
echo "5. Verificando documentación..."
if [ -f "/var/www/html/DOCUMENTACION_SISTEMA_DISPOSITIVOS.md" ]; then
    echo "   ✅ Documentación técnica completa"
else
    echo "   ❌ Documentación NO encontrada"
fi

echo ""
echo "========================================"
echo "VERIFICACIÓN COMPLETADA"
echo "========================================"
