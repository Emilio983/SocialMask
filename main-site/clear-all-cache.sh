#!/bin/bash

echo "🧹 Limpiando todos los cachés..."

# 1. Limpiar caché de PHP OPcache
echo "1. Limpiando OPcache de PHP..."
php -r "if(function_exists('opcache_reset')) opcache_reset();"

# 2. Limpiar caché de Apache
echo "2. Limpiando caché de Apache..."
rm -rf /var/cache/apache2/mod_cache_disk/* 2>/dev/null

# 3. Limpiar archivos temporales
echo "3. Limpiando archivos temporales..."
rm -rf /tmp/php* 2>/dev/null
rm -rf /var/tmp/php* 2>/dev/null

# 4. Limpiar caché de sesiones PHP
echo "4. Limpiando sesiones PHP..."
rm -rf /var/lib/php/sessions/* 2>/dev/null

# 5. Reiniciar Apache
echo "5. Reiniciando Apache..."
systemctl reload apache2

# 6. Actualizar permisos
echo "6. Actualizando permisos..."
chown -R www-data:www-data /var/www/html/assets/
chmod -R 755 /var/www/html/assets/

echo "✅ Caché limpiado completamente!"
echo ""
echo "INSTRUCCIONES PARA LIMPIAR CACHÉ DEL NAVEGADOR:"
echo "================================================"
echo "Edge/Chrome/Brave:"
echo "  1. Presiona: Ctrl + Shift + Delete"
echo "  2. Selecciona: 'Imágenes y archivos en caché'"
echo "  3. Rango: 'Última hora' o 'Todo'"
echo "  4. Click en 'Borrar datos'"
echo "  5. Presiona: Ctrl + F5 para recargar forzadamente"
echo ""
echo "Firefox:"
echo "  1. Presiona: Ctrl + Shift + Delete"
echo "  2. Selecciona: 'Caché'"
echo "  3. Click en 'Limpiar ahora'"
echo "  4. Presiona: Ctrl + F5 para recargar"
echo ""
echo "Alternativa rápida: Modo Incógnito/Privado"
