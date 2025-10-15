# 📜 Scripts de Deployment - Donaciones

Este directorio contiene todos los scripts necesarios para desplegar y gestionar el contrato de donaciones.

---

## 🚀 Scripts Principales

### 1. `deploy-donations.js`
**Propósito:** Desplegar el contrato Donations a blockchain

**Uso:**
```powershell
# Testnet (Amoy)
npx hardhat run scripts/deploy-donations.js --network amoy

# Mainnet (Polygon)
npx hardhat run scripts/deploy-donations.js --network polygon
```

**Qué hace:**
- ✅ Compila contratos
- ✅ Despliega Donations.sol
- ✅ Configura tokens permitidos (SPHE, WMATIC)
- ✅ Verifica en PolygonScan
- ✅ Actualiza frontend automáticamente

**Tiempo:** 2-5 minutos

---

### 2. `update-frontend-addresses.js`
**Propósito:** Actualizar direcciones de contratos en el frontend

**Uso:**
```powershell
node scripts/update-frontend-addresses.js <network> <direccion>

# Ejemplo:
node scripts/update-frontend-addresses.js amoy 0x1234567890abcdef1234567890abcdef12345678
```

**Qué actualiza:**
- `assets/js/donations.js` - Direcciones de contratos
- `deployed-addresses.json` - Registro de direcciones
- `.env` - DONATION_CONTRACT_ADDRESS

**Tiempo:** 5 segundos

---

### 3. `check-balance.js`
**Propósito:** Verificar balance de MATIC antes de desplegar

**Uso:**
```powershell
# Polygon Mainnet
npx hardhat run scripts/check-balance.js --network polygon

# Amoy Testnet
npx hardhat run scripts/check-balance.js --network amoy
```

**Output:**
```
💰 Checking Wallet Balance
============================================================
Network: amoy
Wallet Address: 0xa1052872c755B5B2192b54ABD5F08546eeE6aa20
Balance: 1.5 MATIC
============================================================

✅ Balance sufficient for deployment!
   Estimated gas cost: ~0.3-0.8 MATIC
   Your balance: 1.5 MATIC
```

**Tiempo:** 5 segundos

---

### 4. `check-donations-config.js`
**Propósito:** Verificar configuración del contrato desplegado

**Uso:**
```powershell
node scripts/check-donations-config.js <network> <direccion>

# Ejemplo:
node scripts/check-donations-config.js amoy 0x1234567890abcdef1234567890abcdef12345678
```

**Output:**
```
🔍 Checking Donations Contract Configuration
============================================================
Network: amoy
Contract: 0x1234567890abcdef1234567890abcdef12345678
============================================================

📋 Contract Configuration:
   Owner: 0xa1052872c755B5B2192b54ABD5F08546eeE6aa20
   Treasury: 0xa1052872c755B5B2192b54ABD5F08546eeE6aa20
   Fee Percentage: 2.5 %
   Min Donation: 0.01 tokens

🪙 Allowed Tokens:
   SPHE Token: 0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b
   Status: ✅ Allowed
   WMATIC Token: 0x9c3C9283D3e44854697Cd22D3Faa240Cfb032889
   Status: ✅ Allowed

📊 Statistics:
   Total Campaigns: 0

✅ Contract is properly configured!
```

**Tiempo:** 10 segundos

---

## 📋 Workflow Recomendado

### Primera Vez (Testnet)

```powershell
# 1. Configurar .env con PRIVATE_KEY
notepad .env

# 2. Verificar balance
npx hardhat run scripts/check-balance.js --network amoy

# 3. Desplegar a testnet
npx hardhat run scripts/deploy-donations.js --network amoy

# 4. Verificar configuración
node scripts/check-donations-config.js amoy 0xDIRECCION_DEL_OUTPUT

# 5. Probar en frontend
# Abrir: http://localhost/pages/donations.php
```

### Producción (Mainnet)

```powershell
# 1. Verificar balance (necesitas >0.5 MATIC)
npx hardhat run scripts/check-balance.js --network polygon

# 2. Desplegar a mainnet
npx hardhat run scripts/deploy-donations.js --network polygon

# 3. Verificar configuración
node scripts/check-donations-config.js polygon 0xDIRECCION_DEL_OUTPUT

# 4. Anunciar a usuarios
```

---

## 🔧 Otros Scripts

### `deploy.js`
Deployment del sistema de escrow (anterior)

### `deploy_membership_staking.js`
Deployment del sistema de membresías y staking

### `deploy_gasless_actions.js`
Deployment del sistema de transacciones sin gas

### `verify.js`
Verificación manual de contratos en PolygonScan

---

## ⚠️ Troubleshooting

### Error: "Insufficient funds"
```powershell
# Necesitas MATIC
# Testnet: https://faucet.polygon.technology/
# Mainnet: Comprar en exchange
```

### Error: "Invalid private key"
Verifica en `.env`:
```bash
# ✅ Correcto (sin 0x):
PRIVATE_KEY=abc123def456...

# ❌ Incorrecto:
PRIVATE_KEY=0xabc123def456...
```

### Frontend no se actualizó
```powershell
# Ejecutar manualmente
node scripts/update-frontend-addresses.js <network> <direccion>
```

### Verificación falló
```powershell
# Verificar manualmente (reemplazar parámetros)
npx hardhat verify --network amoy DIRECCION_CONTRATO TREASURY_ADDRESS 250 10000000000000000
```

---

## 📚 Documentación Completa

- **Guía Rápida:** `../MD/fases/FASE_3.1_QUICK_START.md`
- **Guía Completa:** `../MD/fases/FASE_3.1_COMPLETADA.md`
- **Resumen:** `../MD/fases/FASE_3.1_RESUMEN.md`

---

## 💰 Costos Estimados

### Testnet (Amoy)
- Todo: **GRATIS** (usa faucet)

### Mainnet (Polygon)
- Deployment: ~0.3-0.8 MATIC
- Token Allowance: ~0.05 MATIC cada uno
- **Total: ~0.4-0.9 MATIC (~$0.25-0.60 USD)**

---

## ✅ Checklist

Antes de desplegar a mainnet:

- [ ] Probado en testnet (amoy)
- [ ] Todos los tests pasan (29/29)
- [ ] Private key configurada en .env
- [ ] Wallet con >0.5 MATIC
- [ ] Frontend funcionando
- [ ] Event listener probado (FASE 3.4)

---

**¿Dudas?** Ver documentación completa en `MD/fases/`
