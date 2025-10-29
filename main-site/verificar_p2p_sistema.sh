#!/bin/bash

echo "============================================"
echo "🔍 VERIFICACIÓN SISTEMA P2P COMPLETO"
echo "============================================"
echo ""

# Verificar archivos API
echo "📁 Verificando archivos API..."
files=(
    "api/posts/create.php"
    "api/posts/get.php"
    "api/messages/send.php"
    "api/messages/get.php"
    "api/p2p/stats.php"
    "api/p2p/sync.php"
    "api/p2p/public-key.php"
    "api/p2p/save-public-key.php"
    "api/p2p/store-metadata.php"
    "api/p2p/get-metadata.php"
    "api/p2p/receive.php"
    "api/ipfs/upload.php"
    "utils/pinata.php"
    "assets/js/p2p-client.js"
)

for file in "${files[@]}"; do
    if [ -f "/var/www/html/$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file - NO ENCONTRADO"
    fi
done

echo ""
echo "🗄️ Verificando tablas de base de datos..."

# Verificar tablas
mysql -u thesocialmask -e "USE thesocialmask; 
    SELECT 'posts' as tabla, COUNT(*) as existe FROM information_schema.COLUMNS WHERE TABLE_NAME='posts' AND COLUMN_NAME='p2p_mode';
    SELECT 'messages' as tabla, COUNT(*) as existe FROM information_schema.COLUMNS WHERE TABLE_NAME='messages' AND COLUMN_NAME='p2p_mode';
    SELECT 'p2p_metadata' as tabla, COUNT(*) as registros FROM p2p_metadata;
    SELECT 'p2p_stats' as tabla, COUNT(*) as registros FROM p2p_stats;
    SELECT 'ipfs_cache' as tabla, COUNT(*) as registros FROM ipfs_cache;
    SELECT 'user_p2p_preferences' as tabla, COUNT(*) as registros FROM user_p2p_preferences;
" 2>/dev/null | grep -v "tabla\|existe\|registros"

echo ""
echo "🔑 Verificando configuración..."

# Verificar .env
if grep -q "IPFS_API_KEY" /var/www/html/.env; then
    echo "✅ IPFS_API_KEY configurado"
else
    echo "❌ IPFS_API_KEY NO configurado"
fi

if grep -q "IPFS_API_SECRET" /var/www/html/.env; then
    echo "✅ IPFS_API_SECRET configurado"
else
    echo "❌ IPFS_API_SECRET NO configurado"
fi

if grep -q "ZEROX_API_KEY" /var/www/html/.env; then
    echo "✅ ZEROX_API_KEY configurado"
else
    echo "❌ ZEROX_API_KEY NO configurado"
fi

echo ""
echo "🔧 Verificando permisos..."
if [ -w "/var/www/html/api/posts/create.php" ]; then
    echo "✅ Permisos de escritura correctos"
else
    echo "⚠️ Verificar permisos en /var/www/html/api/"
fi

echo ""
echo "============================================"
echo "✅ VERIFICACIÓN COMPLETADA"
echo "============================================"
echo ""
echo "Para probar el sistema:"
echo "1. Ve a tu sitio web"
echo "2. Inicia sesión"
echo "3. Activa el toggle 'P2P' en la navbar"
echo "4. Crea un post con imágenes"
echo "5. Verifica que aparezca badge '🌐 P2P'"
echo ""
