# 🔧 Technical Update - October 19, 2025

## Current Platform Status & Migration Plan

### Production Prototype

**Live Demo:** https://socialmask.org  
**Repository:** https://github.com/Emilio983/SocialMask  
**Current Blockchain:** Polygon Amoy Testnet

The platform is **fully functional** with the following architecture:

**Backend Stack:**
- PHP 8.2-FPM (API REST)
- Node.js 18+ (P2P services)
- MySQL 8.0 (primary database)
- Nginx web server

**Blockchain Integration:**
- **Token SPHE (ERC-20):** `0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b`
- Smart Accounts via Account Abstraction (ERC-4337)
- Gelato Relay with paymaster sponsorship
- Web3Auth for passkey-based authentication
- MetaMask & WalletConnect support

**Decentralized Components:**
- **Gun.js relay** (P2P database) hosted on Glitch
- **IPFS storage** via Pinata API (1GB free tier)
- Signal Protocol for E2E encrypted messaging

**Current Features Working:**
- ✅ Web3 authentication (passkeys + wallet connect)
- ✅ Communities, posts, comments
- ✅ Membership system with SPHE token
- ✅ Anonymous posting capability
- ✅ E2E encrypted direct messages
- ✅ IPFS content storage

---

## ⚠️ Critical Privacy Limitation with Polygon

**The fundamental problem:** While the current implementation successfully demonstrates the platform's functionality and UX, **Polygon cannot protect journalists** because:

### 1. **All Transactions Are Publicly Traceable**

```javascript
// Current donation flow on Polygon (TRANSPARENT)
const tx = await tokenContract.transfer(
  journalistAddress,  // ❌ VISIBLE on block explorer
  donationAmount      // ❌ VISIBLE on block explorer
);
// Result: Anyone can see WHO donated HOW MUCH to WHOM
```

**Real-world threat:** Government surveillance can:
- Track all donations to a journalist's address
- Identify supporters through wallet analysis
- Build a complete financial profile
- Use this data for retaliation

### 2. **Smart Account Abstraction Still Exposes Metadata**

Even with ERC-4337 smart accounts (currently implemented), the following is public:
- UserOperation sender address
- Paymaster interactions
- Token transfers
- Transaction amounts
- Timing patterns

### 3. **Gun.js P2P Layer Provides No Financial Privacy**

Our current Gun.js + IPFS architecture protects:
- ✅ Content (via IPFS CID)
- ✅ Messages (via Signal Protocol E2E encryption)

But does NOT protect:
- ❌ Financial transactions
- ❌ Donation patterns
- ❌ Token holdings
- ❌ Transaction history

---

## 🔐 Zcash Integration - Technical Migration Plan

### Why Zcash Is Essential (Not Optional)

Zcash shielded transactions provide what Polygon cannot:

```javascript
// Zcash shielded transaction (PRIVATE)
const shieldedTx = await zcash.sendShielded({
  from: z_address1,     // 🔒 HIDDEN
  to: z_address2,       // 🔒 HIDDEN
  amount: value,        // 🔒 HIDDEN
  memo: encryptedData   // 🔒 HIDDEN (encrypted)
});
// Result: ZERO public information on blockchain
```

**Critical differences:**

| Feature | Polygon | Zcash Shielded |
|---------|---------|----------------|
| Sender address | ❌ Public | ✅ Hidden |
| Receiver address | ❌ Public | ✅ Hidden |
| Amount | ❌ Public | ✅ Hidden |
| Transaction history | ❌ Traceable | ✅ Private |
| Metadata | ❌ Exposed | ✅ Encrypted memo field |

---

## 📐 Architectural Changes Required

### Phase 1: Backend Infrastructure (Milestone 1)

**Current:**
```javascript
// backend-node/src/services/blockchain.js (Polygon)
const provider = new ethers.JsonRpcProvider(POLYGON_RPC_HTTP_URL);
const wallet = new ethers.Wallet(PRIVATE_KEY, provider);
```

**Migrating to:**
```javascript
// backend-node/src/services/zcash.js (NEW)
const { Zcash } = require('zcash-js-sdk');

class ZcashService {
  constructor() {
    this.node = new Zcash.RpcClient({
      url: process.env.ZCASH_RPC_URL,
      user: process.env.ZCASH_RPC_USER,
      pass: process.env.ZCASH_RPC_PASS
    });
  }

  // Generate shielded z-address for journalist
  async generateZAddress() {
    return await this.node.z_getnewaddress('sapling');
  }

  // Send shielded donation
  async sendShieldedDonation(fromZAddr, toZAddr, amount, memo) {
    const txid = await this.node.z_sendmany(
      fromZAddr,
      [{ address: toZAddr, amount, memo }],
      1, // minconf
      0.0001 // fee
    );
    return txid;
  }

  // Get shielded balance
  async getShieldedBalance(zAddr) {
    return await this.node.z_getbalance(zAddr);
  }
}
```

### Phase 2: Database Schema Changes

**New tables required:**

```sql
-- Store journalist shielded addresses
CREATE TABLE journalist_zcash_addresses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  z_address VARCHAR(78) NOT NULL, -- Sapling z-address
  viewing_key VARCHAR(256), -- Optional: for transparent viewing
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE(z_address)
);

-- Track shielded donations (only metadata, amounts are private)
CREATE TABLE shielded_donations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  txid VARCHAR(64) NOT NULL,
  journalist_user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending', 'confirmed') DEFAULT 'pending',
  confirmations INT DEFAULT 0,
  FOREIGN KEY (journalist_user_id) REFERENCES users(id),
  UNIQUE(txid)
);
```

### Phase 3: Frontend Integration

**Current Polygon integration:**
```javascript
// components/WalletConnect.js (Polygon)
const provider = new ethers.BrowserProvider(window.ethereum);
const signer = await provider.getSigner();
const contract = new ethers.Contract(TOKEN_ADDRESS, ABI, signer);
await contract.transfer(recipientAddress, amount);
```

**New Zcash integration (zTip.js SDK):**
```javascript
// components/ZcashTipping.js (NEW)
import { ZcashTip } from './lib/zTip.js';

const zcashTip = new ZcashTip({
  rpcUrl: 'https://api.thesocialmask.org/zcash',
  network: 'mainnet'
});

// Journalist displays their z-address
const zAddress = journalist.zcash_address; // From backend

// Reader sends shielded tip
async function sendShieldedTip(amount, memo) {
  const tx = await zcashTip.sendShielded({
    to: zAddress,
    amount: amount,
    memo: memo // Encrypted message to journalist
  });
  
  // Show confirmation UI
  showConfirmation(tx.txid);
}
```

### Phase 4: Smart Contract Migration Strategy

**Challenge:** Cannot directly port Gelato paymaster model to Zcash (different architecture).

**Solution:** Hybrid approach:

1. **For micropayments (<$5):** Pure Zcash shielded
   - Direct z-to-z transactions
   - No intermediaries
   - Maximum privacy

2. **For larger donations (>$5):** Optional transparent wrapper
   - User choice: privacy vs. features
   - Bridge contract on Polygon (if needed)
   - Maintain shielded option as default

**Current escrow system:**
```solidity
// escrow-system/contracts/SurveyEscrow.sol (Polygon)
contract SurveyEscrow {
  mapping(uint => Escrow) public escrows;
  // ... transparent implementation
}
```

**Not directly porting to Zcash.** Instead:
- Keep Polygon for complex escrow features
- Use Zcash for journalist donations (primary use case)
- Clear UX explaining difference

---

## 🛠️ Development Roadmap

### Week 1 (Oct 21-27): Testnet Setup
- ✅ Deploy Zcash testnet node on VPS
- ✅ Configure RPC credentials
- ✅ Test z-address generation
- ✅ Complete 100+ test transactions z-to-z

### Week 2 (Oct 28 - Nov 3): Backend Integration
- Implement ZcashService class
- Create journalist z-address generation endpoint
- Build shielded transaction relay API
- Database schema migration

### Week 3 (Nov 4-10): Frontend Development
- Develop zTip.js SDK (open-source)
- Build Zcash wallet UI components
- Transaction confirmation flow
- Error handling & edge cases

### Week 4 (Nov 11-17): Testing & Security
- Penetration testing on shielded flows
- Transaction reliability tests
- Edge case handling
- Documentation

---

## 📊 Success Metrics (Milestone 1)

By December 15, 2025:

- ✅ **100+ successful z-to-z shielded transactions** on testnet
- ✅ **zTip.js SDK functional** with documentation
- ✅ **Zcash nodes stable** (>99% uptime monitored)
- ✅ **10+ beta testers** confirm anonymity works
- ✅ **Monthly progress report** posted on Zcash forum

---

## 🔗 Technical Resources

**Current codebase references:**
- Backend Node.js: `backend-node/src/services/blockchain.js`
- Smart contracts: `escrow-system/contracts/`
- Frontend wallet: `components/WalletConnect.js`
- Database schema: `database/schema.sql`

**New Zcash components (in development):**
- `backend-node/src/services/zcash.js` (NEW)
- `components/lib/zTip.js` (NEW SDK)
- `database/migrations/004_zcash_addresses.sql` (NEW)
- `api/zcash/` (NEW REST endpoints)

---

## 💬 Open for Feedback

I'm actively seeking technical feedback on:

1. **Best practices for Zcash RPC integration** in Node.js backend
2. **Optimal z-address generation strategy** for multi-user platform
3. **Transaction monitoring patterns** for shielded transactions
4. **SDK architecture** for zTip.js (want it to be ecosystem-wide tool)

Please comment with any suggestions, concerns, or recommendations.

This migration is critical - without Zcash, the platform cannot fulfill its core mission of protecting journalists.

---

**Next update:** October 26, 2025 (Testnet integration progress)



# 🌐 thesocialmask - Red Social Descentralizada P2P

**Plataforma social Web3 con token SPHE, mensajería E2E y anonimato ZKP**

> ⚠️ **IMPORTANTE**: Este proyecto está configurado para deployment en **VPS Cloudy**
> 📡 **Servidor**: TheSocialMask (45.61.148.251) - Ubuntu Server 24.04 LTS
> 📋 **Guía completa**: Ver `CLOUDY_VPS_SETUP.md` para instrucciones de deployment

---

## 📂 ESTRUCTURA DEL PROYECTO

```
thesocialmask/
├── 📄 desarrollador.md          ← GUÍA PRINCIPAL DEL DESARROLLADOR
├── 🤖 comandosclaude.md         ← PROMPTS PARA CLAUDE CODE
├── ⚙️ administrador.md          ← SETUP DE HERRAMIENTAS Y API KEYS
├── 🚀 CLOUDY_VPS_SETUP.md       ← DEPLOYMENT EN VPS CLOUDY (LEER PRIMERO)
├── 🖥️ VPS_SETUP.md              ← Guía general de VPS
│
├── 📁 MD/                       ← Documentación técnica (.md)
│   ├── ANALYSIS.md
│   ├── FUNCTIONS_LIST.md
│   ├── MASTER_PLAN_MIGRATION.md
│   ├── PLAN_MAESTRO_DESCENTRALIZACION.md
│   ├── RESUMEN_EJECUTIVO_P2P.md
│   ├── TAREAS_CLAUDE_CODE.md
│   ├── TASKS_CHECKLIST.md
│   └── WEEK_1_TASKS.md
│
├── 📁 TXT/                      ← Documentación adicional (.txt)
│   ├── COMO_USAR_LEARN_SYSTEM.txt
│   ├── PAYMENT_FLOWS_VERIFIED.txt
│   ├── RESPONSIVE_IMPLEMENTATION.txt
│   ├── RESUMEN_FINAL_SESION.txt
│   ├── TAREAS_PENDIENTES_PROXIMO_CHAT.txt
│   └── VERIFICACION_FINAL_COMPLETA.txt
│
├── 📁 api/                      ← Backend PHP APIs
├── 📁 backend-node/             ← Backend Node.js P2P (crear)
├── 📁 components/               ← Componentes reutilizables
├── 📁 config/                   ← Configuración
├── 📁 database/                 ← Schema SQL
├── 📁 escrow-system/            ← Smart Contracts
├── 📁 pages/                    ← Frontend PHP
└── 📁 uploads/                  ← Archivos (migrar a IPFS)
```

---

## 🚀 INICIO RÁPIDO

### 🔴 PASO 0: Conectar al VPS Cloudy (OBLIGATORIO)

**Antes de todo, conecta a tu servidor**:
```bash
# SSH al VPS Cloudy
ssh root@45.61.148.251
# Password: TVB1dsmjQ526iH

# Verificar sistema
uname -a  # Ubuntu 24.04 LTS
free -h   # RAM: 1GB (limitado)
df -h     # Disco: 25GB SSD
```

> ⚠️ **IMPORTANTE**: Con 1GB RAM, debes usar servicios externos (Glitch para Gun.js, Pinata para IPFS)
> Ver `CLOUDY_VPS_SETUP.md` para configuración optimizada

### 1. Para Desarrolladores (en VPS)

**Lee primero**:
1. 🚀 **`CLOUDY_VPS_SETUP.md`** - Deployment en tu VPS Cloudy (LEER PRIMERO)
2. 📄 **`desarrollador.md`** - Visión completa del proyecto
3. 🤖 **`comandosclaude.md`** - Copia/pega prompts para implementar features (adaptados a VPS)
4. ⚙️ **`administrador.md`** - Configura herramientas y API keys

**Empezar deployment en VPS**:
```bash
# EN EL VPS (después de conectar por SSH)
cd /var/www/thesocialmask

# Leer el plan de la semana 1 (adaptado a VPS)
cat MD/WEEK_1_TASKS.md

# Ejecutar script de deployment automático
bash CLOUDY_VPS_SETUP.md  # Sección "Script de Deployment Rápido"
```

### 2. Para Administradores (Configurar VPS)

**Configurar entorno VPS**:
1. Conectar por SSH a VPS Cloudy
2. Abrir **`CLOUDY_VPS_SETUP.md`**
3. Ejecutar script de deployment completo
4. Configurar servicios externos:
   - Glitch.com → Gun.js relay (gratis)
   - Pinata → IPFS storage (gratis)
5. Obtener API keys (ver `administrador.md`)
6. Configurar SSL con Certbot

---

## 📊 ESTADO ACTUAL

### ✅ Implementado (60%)
- Backend PHP + MySQL
- Autenticación Web3 (MetaMask, WalletConnect)
- Comunidades, posts, comentarios
- Sistema de membresías
- Smart contract SurveyEscrow
- Admin panel

### ❌ Por Implementar (40%)
- Sistema P2P (Gun.js + IPFS)
- Mensajes E2E encriptados
- Identidad anónima (ZKP)
- Pago automático por visitas
- Sistema de periodistas
- Donaciones multi-crypto

**Ver progreso detallado**: `desarrollador.md`

---

## 🛠️ STACK TECNOLÓGICO

### Infraestructura (VPS Cloudy)
- **VPS**: Ubuntu Server 24.04 LTS (1GB RAM, 1 vCPU, 25GB SSD)
- **Web Server**: Nginx (optimizado para bajo consumo)
- **Gestión**: PM2 para procesos Node.js
- **SSL**: Certbot (Let's Encrypt)
- **Dominio**: A configurar → 45.61.148.251

### Backend (en VPS /var/www/thesocialmask)
- **PHP 8.2-FPM**: API REST legacy (optimizado, max 5 children)
- **Node.js 18+**: Backend P2P (Gun.js, IPFS)
- **MySQL 8.0**: Base de datos central (128MB buffer_pool)

### Backend P2P (Servicios Externos - 1GB RAM limitado)
- **Gun.js Relay**: Glitch.com (gratis, siempre activo)
- **IPFS**: Pinata.cloud (gratis, 1GB storage)
- **OrbitDB**: Opcional cuando se upgradie VPS

### Frontend
- **HTML + TailwindCSS + Alpine.js**: UI
- **Web3.js**: Wallet integration
- **Gun.js Client**: P2P realtime

### Blockchain
- **Polygon**: Smart contracts
- **Token SPHE**: ERC-20 (0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b)
- **Hardhat**: Deploy & testing

### P2P & Privacy
- **Gun.js**: Base de datos P2P (relay en Glitch)
- **IPFS**: Almacenamiento descentralizado (Pinata API)
- **Signal Protocol**: E2E encryption
- **Circom/SnarkJS**: Zero-Knowledge Proofs

---

## 📚 DOCUMENTACIÓN

### 📄 Archivos Principales (Raíz)

| Archivo | Descripción | Para Quién |
|---------|-------------|------------|
| `CLOUDY_VPS_SETUP.md` | 🚀 **DEPLOYMENT EN VPS CLOUDY** | **LEER PRIMERO** |
| `VPS_SETUP.md` | Guía general de VPS | Admins/DevOps |
| `desarrollador.md` | Guía completa del proyecto | Desarrolladores |
| `comandosclaude.md` | 40 prompts listos para Claude (VPS) | Todos |
| `administrador.md` | Setup de herramientas y API keys (VPS) | Admins/DevOps |

### 📁 Carpeta MD/ (Documentación Técnica)

| Archivo | Contenido |
|---------|-----------|
| `ANALYSIS.md` | Análisis del proyecto actual |
| `MASTER_PLAN_MIGRATION.md` | Plan de migración a P2P |
| `TASKS_CHECKLIST.md` | 87 tareas con checkboxes |
| `FUNCTIONS_LIST.md` | 163 funciones a crear/modificar |
| `WEEK_1_TASKS.md` | Guía día por día semana 1 |

### 📁 Carpeta TXT/ (Notas y Resúmenes)

Documentación adicional de sesiones previas y verificaciones.

---

## 🎯 ROADMAP

### Semana 1-2: Infraestructura P2P
- [ ] Backend Node.js
- [ ] Gun.js relay
- [ ] IPFS node
- [ ] OrbitDB

### Semana 3-4: Smart Contracts
- [ ] PayPerView.sol
- [ ] InfiltratorContract.sol
- [ ] DonationPool.sol

### Semana 5-6: Posts P2P
- [ ] Migrar posts a Gun.js
- [ ] Media en IPFS
- [ ] Switch modo P2P

### Semana 7-8: E2E Encryption
- [ ] Signal Protocol
- [ ] Chat encriptado
- [ ] Mensajes autodestructivos

### Semana 9-10: Anonimato
- [ ] Circom circuits
- [ ] Post anónimo
- [ ] Reputación ZKP

### Semana 11-12: Features Finales
- [ ] Sistema periodistas
- [ ] Donaciones
- [ ] Deploy producción

**Ver roadmap completo**: `MD/MASTER_PLAN_MIGRATION.md`

---

## 🤖 CÓMO USAR CON CLAUDE CODE

### Método 1: Prompts Individuales

1. Abrir `comandosclaude.md`
2. Copiar comando específico (ej: Comando 4 - Gun.js Relay)
3. Pegar en Claude Code
4. Ejecutar y verificar

### Método 2: Prompt Completo

Copiar el "Prompt Final: Proyecto Completo" de `comandosclaude.md` y Claude implementará TODO el proyecto paso a paso.

### Método 3: Por Fases

1. Semana 1: Comandos 1-7
2. Semana 2: Comandos 8-11
3. Semana 3: Comandos 12-15
...y así sucesivamente.

---

## 🔑 CONFIGURACIÓN INICIAL (EN VPS CLOUDY)

### 1. Conectar y Preparar VPS

```bash
# 1. Conectar al VPS
ssh root@45.61.148.251
# Password: TVB1dsmjQ526iH

# 2. Actualizar sistema
apt update && apt upgrade -y

# 3. Instalar stack base
apt install -y nginx mysql-server php8.2-fpm php8.2-mysql nodejs npm git certbot python3-certbot-nginx

# 4. Configurar swap (2GB para compensar 1GB RAM)
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# 5. Clonar repositorio
cd /var/www
git clone https://github.com/tu-usuario/thesocialmask.git
cd thesocialmask
```

### 2. Configurar Servicios Externos (1GB RAM limitado)

> ⚠️ **IMPORTANTE**: No corras Gun.js/IPFS localmente con 1GB RAM

**Gun.js Relay en Glitch (Gratis)**:
1. Ir a https://glitch.com
2. Crear cuenta
3. Nuevo proyecto Node.js
4. Pegar código de `CLOUDY_VPS_SETUP.md` → Sección "Gun.js en Glitch"
5. Tu relay: `https://tu-proyecto.glitch.me`

**IPFS en Pinata (Gratis - 1GB)**:
1. Ir a https://pinata.cloud
2. Crear cuenta gratuita
3. API Keys → New Key
4. Guardar: `PINATA_API_KEY` y `PINATA_SECRET_KEY`

### 3. Configurar .env en VPS

```bash
cd /var/www/thesocialmask
cp .env.example .env   # solo la primera vez
nano .env
```

Completa los bloques clave:

- **Base de datos**: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- **Seguridad**: genera `JWT_SECRET` y `SESSION_SECRET` largos y únicos.
- **Polygon**: `POLYGON_RPC_HTTP_URL`, `POLYGON_RPC_WSS_URL`, `CHAIN_ID`.
- **Web3Auth (passkeys)**: `WEB3AUTH_CLIENT_ID`, `WEB3AUTH_CLIENT_SECRET`, `WEB3AUTH_JWKS_ENDPOINT`.
- **Gelato (smart accounts y paymaster)**: `GELATO_RELAY_API_KEY`, `ERC4337_BUNDLER_RPC_URL`, `PAYMASTER_RPC_URL`, `PAYMASTER_POLICY_ID`, `GAS_SPONSOR_DAILY_USD_LIMIT`, `TXS_PER_DAY_LIMIT`.
- **Smart accounts (AA)**: `SIMPLE_ACCOUNT_FACTORY_ADDRESS`, `ENTRY_POINT_ADDRESS`.
- **Swaps**: `SWAP_API_URL`, `ZEROX_API_KEY`, `FALLBACK_PREFERRED`, `QUICKSWAP_ROUTER_ADDRESS`, `UNISWAP_V3_POOL_SPHE_USDT`.
- **Tokens**: `MY_TOKEN_ADDRESS`, `MY_TOKEN_SYMBOL`, `MY_TOKEN_DECIMALS`, `USDT_ADDRESS`, `USDT_DECIMALS`.

`config/env.php` carga y valida estas variables en tiempo de ejecución; cualquier falta se reflejará inmediatamente en los logs.

Ver **`CLOUDY_VPS_SETUP.md`** para configuración completa paso a paso.

### 4. Backend Node (smart accounts, swaps)

```bash
cd backend-node
npm install
npm run dev   # entorno de desarrollo

# producción
npm run build
pm2 start dist/server.js --name thesocialmask-node
```

El servicio Node consume las mismas variables declaradas en `.env`; valida la configuración al iniciar y expone endpoints REST (`/auth`, `/devices`, `/receive`, `/swap`, `/withdraw`). Comparte la conexión MySQL para operar sobre `smart_accounts`, `user_devices` y `device_links`.

### 5. Deployment Automático

```bash
# En el VPS: Ejecutar script de deployment
cd /var/www/thesocialmask
chmod +x deploy.sh
./deploy.sh

# O seguir manualmente CLOUDY_VPS_SETUP.md
```

### 6. Verificar Servicios

```bash
# Nginx
systemctl status nginx

# PHP-FPM
systemctl status php8.2-fpm

# MySQL
systemctl status mysql

# Acceder a tu sitio
# http://45.61.148.251 (o tu dominio si ya lo configuraste)
```

### 7. Migraciones de Base de Datos

Cada nueva versión incluye archivos en `database/migrations/`. Para aplicar la migración de smart accounts y dispositivos:

```bash
mysql -u root -p thesocialmask < database/migrations/003_smart_accounts_and_aliases.sql
```

Realiza respaldo antes de ejecutar en producción.

---

## 📞 SOPORTE

### Problemas Técnicos
- Ver `administrador.md` → Sección "Troubleshooting"
- Ejecutar comando de debug de `comandosclaude.md`

### Documentación
- **Visión general**: `desarrollador.md`
- **Tareas específicas**: `MD/TASKS_CHECKLIST.md`
- **Código paso a paso**: `MD/WEEK_1_TASKS.md`

### Claude Code
- **40 prompts listos**: `comandosclaude.md`
- **Copiar/pegar y ejecutar**

---

## 📝 CONVENCIONES

### Commits
```bash
git commit -m "feat: Add Gun.js relay server"
git commit -m "fix: Resolve IPFS upload issue"
git commit -m "docs: Update developer guide"
```

### Branches
- `main`: Producción
- `develop`: Desarrollo
- `feature/gun-relay`: Features nuevas
- `hotfix/security`: Fixes urgentes

### Code Style
- **PHP**: PSR-12
- **JavaScript**: StandardJS
- **Solidity**: Solhint

---

## 🏆 CONTRIBUTORS

- **Desarrollador Principal**: [Tu nombre]
- **Arquitecto**: Claude Code
- **Documentación**: Auto-generada

---

## 📜 LICENSE

MIT License - Ver LICENSE file

---

## 🔗 LINKS ÚTILES

- **Token SPHE**: [0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b](https://polygonscan.com/token/0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b)
- **Gun.js Docs**: https://gun.eco/docs/
- **IPFS Docs**: https://docs.ipfs.tech/
- **Signal Protocol**: https://signal.org/docs/
- **Circom**: https://docs.circom.io/

---

## ⚡ QUICK COMMANDS (EN VPS)

```bash
# Conectar al VPS
ssh root@45.61.148.251

# Navegar al proyecto
cd /var/www/thesocialmask

# Ver archivos organizados
ls MD/          # Docs técnicos
ls TXT/         # Notas adicionales

# Leer guías principales
cat CLOUDY_VPS_SETUP.md   # Setup VPS (PRIMERO)
cat desarrollador.md      # Guía completa
cat comandosclaude.md     # Prompts Claude (VPS)
cat administrador.md      # Setup herramientas

# Empezar desarrollo
cat MD/WEEK_1_TASKS.md   # Tareas semana 1 (VPS)

# Logs del sistema
tail -f /var/log/nginx/error.log
tail -f /var/log/mysql/error.log

# Reiniciar servicios
systemctl restart nginx
systemctl restart php8.2-fpm
systemctl restart mysql
```

---

**🚀 PRÓXIMO PASO**:
1. Conectar al VPS: `ssh root@45.61.148.251`
2. Leer `CLOUDY_VPS_SETUP.md` para deployment completo
3. Leer `desarrollador.md` para entender el proyecto
4. Ejecutar comandos de `comandosclaude.md` (adaptados a VPS)

**📡 Servidor**: TheSocialMask @ Cloudy (Las Vegas)
**🌐 IP**: 45.61.148.251
**💾 Specs**: 1GB RAM, 1 vCPU, 25GB SSD, Ubuntu 24.04 LTS

**Última actualización**: 2025-10-05
### 8. Passkeys y Smart Accounts

- El login se realiza vía `/api/auth/passkey_start.php` y `/api/auth/passkey_finish.php`.
- El servicio Node (`backend-node`) provisiona cuentas inteligentes y gestiona swaps.
- Asegura que `NODE_BACKEND_BASE_URL` apunte al host/puerto correcto. Por defecto: `http://127.0.0.1:3088`.
- Para recuperar la clave EVM real se debe enviar un `idToken` válido de Web3Auth; el frontend puede exponer `window.obtainWeb3AuthToken({ challengeId, credential })` y retornarlo como `{ idToken }` para que el backend lo valide contra `WEB3AUTH_JWKS_ENDPOINT`.
- El backend persiste `paymaster_policy_id` en `smart_accounts`; llena `PAYMASTER_POLICY_ID` en `.env` para aplicar límites del paymaster de Gelato.
- Nuevos endpoints de mantenimiento: `GET /devices/smart-account/status` para consultar estado/`deployment_tx_hash` y `POST /devices/smart-account/redeploy` para reintentar un despliegue pendiente.
- Alias obligatorio desde `pages/onboarding/alias.php` (consume `/api/auth/set_alias.php`).
- Gestión de dispositivos en `pages/wallet/devices.php` con endpoints `/api/devices/*`.
