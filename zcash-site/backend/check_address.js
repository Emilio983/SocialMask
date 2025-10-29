#!/usr/bin/env node

/**
 * Script para verificar la dirección t1 de un usuario
 */

import { generateWalletAddresses } from './src/services/address-generator.js';

const userId = 'fe635c47-ab11-4c20-9324-e9fd5317adc6';

console.log('🔍 Generando direcciones para usuario:', userId);
console.log('');

const addresses = generateWalletAddresses(userId);

console.log('📋 Direcciones generadas:');
console.log('');
console.log('  Transparent Address (t1):');
console.log('  ' + addresses.tAddr);
console.log('');
console.log('  Unified Address (u1):');
console.log('  ' + addresses.ua);
console.log('');
console.log('  Validación:', addresses.isValid ? '✅ Válida' : '❌ Inválida');
console.log('');
console.log('💡 Esta es la dirección t1 donde deberías haber enviado los ZEC.');
console.log('   Si enviaste a esta dirección, podemos verificar el balance.');
