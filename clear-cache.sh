#!/bin/bash
# Script para limpiar todo el cache del servidor

echo "=== Limpiando Cache del Servidor ==="

# 1. Limpiar cache de PHP OPcache
echo "1. Limpiando PHP OPcache..."
if [ -f /var/www/html/clear_opcache.php ]; then
    php /var/www/html/clear_opcache.php
    echo "   ✓ OPcache limpiado via PHP"
fi

# 2. Limpiar cache de Nginx
echo "2. Limpiando cache de Nginx..."
if [ -d /var/cache/nginx ]; then
    rm -rf /var/cache/nginx/*
    echo "   ✓ Cache de Nginx limpiado"
fi

# 3. Limpiar cache de FastCGI
echo "3. Limpiando cache de FastCGI..."
if [ -d /var/run/php ]; then
    find /var/run/php -name "*.sock" -exec touch {} \;
    echo "   ✓ Sockets PHP tocados"
fi

# 4. Recargar servicios
echo "4. Recargando servicios..."
systemctl reload nginx 2>/dev/null && echo "   ✓ Nginx recargado" || echo "   ✗ Error recargando Nginx"
systemctl reload php8.3-fpm 2>/dev/null && echo "   ✓ PHP-FPM recargado" || echo "   - PHP-FPM no disponible"

echo ""
echo "=== Cache del servidor limpiado ==="
echo ""
echo "Para limpiar el cache del navegador Edge:"
echo "1. Presiona Ctrl + Shift + Delete"
echo "2. Selecciona 'Todo el tiempo' en el rango de tiempo"
echo "3. Marca 'Imágenes y archivos almacenados en caché'"
echo "4. Haz clic en 'Borrar ahora'"
echo ""
echo "O bien, usa Ctrl + F5 para recargar la página sin cache"
