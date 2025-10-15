#!/bin/bash

echo "🔍 VERIFICANDO REFERENCIAS A METAMASK..."
echo "=========================================="
echo ""

# Verificar window.ethereum
echo "1. Buscando window.ethereum en archivos JS..."
count1=$(grep -r "window\.ethereum" assets/js/*.js assets/js/*/*.js 2>/dev/null | grep -v ".bak" | grep -v "node_modules" | wc -l)
if [ $count1 -eq 0 ]; then
    echo "   ✅ No se encontraron referencias a window.ethereum"
else
    echo "   ❌ Se encontraron $count1 referencias"
    grep -rn "window\.ethereum" assets/js/*.js assets/js/*/*.js 2>/dev/null | grep -v ".bak" | grep -v "node_modules"
fi

echo ""

# Verificar MetaMask
echo "2. Buscando 'MetaMask' en archivos JS..."
count2=$(grep -r "MetaMask" assets/js/*.js assets/js/*/*.js 2>/dev/null | grep -v ".bak" | grep -v "node_modules" | wc -l)
if [ $count2 -eq 0 ]; then
    echo "   ✅ No se encontraron referencias a MetaMask"
else
    echo "   ⚠️  Se encontraron $count2 referencias (pueden ser en comentarios)"
    grep -rn "MetaMask" assets/js/*.js assets/js/*/*.js 2>/dev/null | grep -v ".bak" | grep -v "node_modules"
fi

echo ""

# Verificar uso de smartWalletProvider
echo "3. Verificando uso de smartWalletProvider..."
count3=$(grep -r "smartWalletProvider" assets/js/*.js assets/js/*/*.js 2>/dev/null | grep -v ".bak" | wc -l)
if [ $count3 -gt 0 ]; then
    echo "   ✅ Sistema usando smartWalletProvider ($count3 referencias)"
else
    echo "   ⚠️  No se encontró smartWalletProvider"
fi

echo ""
echo "=========================================="
echo "✅ VERIFICACIÓN COMPLETADA"
echo ""

if [ $count1 -eq 0 ] && [ $count3 -gt 0 ]; then
    echo "🎉 RESULTADO: Sistema 100% migrado a Smart Wallets"
else
    echo "⚠️  RESULTADO: Revisar archivos arriba"
fi

