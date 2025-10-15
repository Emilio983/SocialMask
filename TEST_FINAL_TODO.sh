#!/bin/bash

echo "=========================================="
echo "   PRUEBA FINAL DE TODAS LAS PÁGINAS"
echo "=========================================="
echo ""

HOST=socialmask.org

echo "🌐 Testing all pages..."
echo ""

# Pages to test
pages=(
  "/contact:Contacto"
  "/governance:Governance"
  "/devices:Dispositivos"
  "/swap:Swap"
  "/token:Token"
  "/learn:Learn"
  "/membership:Membership"
  "/profile:Profile"
  "/donations:Donaciones"
)

echo "| Página | HTTP | HTTPS | Estado |"
echo "|--------|------|-------|--------|"

for page_info in "${pages[@]}"; do
  IFS=':' read -r path name <<< "$page_info"
  
  http_code=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1$path)
  https_code=$(curl --resolve "$HOST:443:127.0.0.1" -k -s -o /dev/null -w '%{http_code}' https://$HOST$path)
  
  if [[ "$http_code" == "200" ]] || [[ "$http_code" == "302" ]]; then
    status="✅"
  else
    status="❌"
  fi
  
  printf "| %-15s | %s | %s | %s |\n" "$name" "$http_code" "$https_code" "$status"
done

echo ""
echo "=========================================="
echo "   SERVICIOS"
echo "=========================================="
echo ""

# Backend Node.js
if pgrep -f "node.*server.js" > /dev/null; then
  echo "✅ Backend Node.js corriendo (puerto 3088)"
else
  echo "❌ Backend Node.js NO corriendo"
fi

# Nginx
if systemctl is-active --quiet nginx; then
  echo "✅ Nginx activo"
else
  echo "❌ Nginx inactivo"
fi

# PHP-FPM
if systemctl is-active --quiet php8.4-fpm; then
  echo "✅ PHP 8.4-FPM activo"
else
  echo "❌ PHP 8.4-FPM inactivo"
fi

echo ""
echo "=========================================="
echo "   ARCHIVOS MODIFICADOS HOY"
echo "=========================================="
echo ""

find /var/www/html/pages /var/www/html/assets/js/governance -type f -mmin -120 -ls 2>/dev/null | \
  awk '{print "📝", $11}' | head -n 20

echo ""
echo "=========================================="
echo "✅ PRUEBA COMPLETADA"
echo "=========================================="
echo ""
echo "Para ver los cambios:"
echo "1. Abre tu navegador"
echo "2. Ve a https://socialmask.org/governance"
echo "3. Presiona Ctrl+F5 (forzar recarga)"
echo "4. Verifica que todo sea dark theme"
echo ""
