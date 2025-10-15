#!/bin/bash

echo "============================================"
echo "🧹 LIMPIANDO CACHÉ DEL SERVIDOR"
echo "============================================"
echo ""

# Limpiar caché de Nginx
echo "📦 Limpiando caché de Nginx..."
if [ -d "/var/cache/nginx" ]; then
    rm -rf /var/cache/nginx/*
    echo "✅ Caché de Nginx limpiado"
else
    echo "⚠️ Directorio de caché de Nginx no encontrado"
fi

# Limpiar caché de PHP
echo "📦 Limpiando caché de PHP OPcache..."
if [ -d "/var/cache/php" ]; then
    rm -rf /var/cache/php/*
    echo "✅ Caché de PHP limpiado"
fi

# Limpiar caché de Apache (si existe)
if [ -d "/var/cache/apache2" ]; then
    rm -rf /var/cache/apache2/mod_cache_disk/*
    echo "✅ Caché de Apache limpiado"
fi

# Recargar Nginx
echo "🔄 Recargando Nginx..."
systemctl reload nginx
echo "✅ Nginx recargado"

# Limpiar sesiones PHP viejas
echo "🗑️ Limpiando sesiones PHP antiguas..."
if [ -d "/var/lib/php/sessions" ]; then
    find /var/lib/php/sessions -type f -mtime +7 -delete
    echo "✅ Sesiones antiguas eliminadas"
fi

echo ""
echo "============================================"
echo "✅ CACHÉ DEL SERVIDOR LIMPIADO"
echo "============================================"
echo ""
echo "Ahora limpia el caché del navegador:"
echo "1. Ctrl + Shift + Delete"
echo "2. Seleccionar 'Desde siempre'"
echo "3. Marcar todo"
echo "4. Click 'Borrar ahora'"
echo "5. Cerrar y reabrir navegador"
echo ""
