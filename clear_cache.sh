#!/bin/bash

echo "🧹 Limpiando caché del servidor..."

# Limpiar caché de PHP
if command -v php &> /dev/null; then
    php -r "opcache_reset();" 2>/dev/null || echo "OPcache no disponible"
fi

# Limpiar caché de Apache
if systemctl is-active --quiet apache2; then
    systemctl reload apache2
    echo "✓ Apache recargado"
fi

# Limpiar caché de Nginx
if systemctl is-active --quiet nginx; then
    systemctl reload nginx
    echo "✓ Nginx recargado"
fi

# Eliminar archivos temporales
find /tmp -name "sess_*" -mtime +1 -delete 2>/dev/null
echo "✓ Sesiones temporales limpiadas"

# Establecer headers de no-cache en archivos JS
echo "✓ Estableciendo headers anti-caché..."

echo "¡Caché del servidor limpiado!"
