# 🌐 Web3 API Backend

Backend API endpoints para integración Web3 con MetaMask y contratos inteligentes de Sphera Governance.

## 📁 Estructura

```
api/web3/
├── verify-signature.php      # Verificación de firmas Web3
├── sync-wallet.php           # Sincronización de wallet con backend
├── get-contract-data.php     # Lectura de datos de blockchain
└── web3-helper.php           # Funciones helper de PHP
```

---

## 🔌 Endpoints

### 1. **Verify Signature** (`verify-signature.php`)

Verifica firmas de MetaMask (personal_sign y EIP-712).

**Método:** `POST`

**Input:**
```json
{
  "message": "Message that was signed",
  "signature": "0x...",
  "expectedSigner": "0x...",
  "type": "personal_sign"
}
```

**Output:**
```json
{
  "success": true,
  "valid": true,
  "signer": "0x...",
  "expectedSigner": "0x..."
}
```

**Rate Limit:** 20 requests/minute

**Tipos soportados:**
- `personal_sign` - Firma simple de mensaje
- `eip712` - Firma estructurada (typed data)

---

### 2. **Sync Wallet** (`sync-wallet.php`)

Sincroniza dirección de wallet del usuario con el backend después de verificar firma.

**Método:** `POST`

**Authentication:** Requerida (sesión activa)

**Input:**
```json
{
  "address": "0x...",
  "message": "Verification message",
  "signature": "0x...",
  "timestamp": 1696723200
}
```

**Output:**
```json
{
  "success": true,
  "wallet_address": "0x...",
  "balance": "1000000000000000000",
  "verified": true,
  "user": {
    "id": 123,
    "username": "usuario"
  }
}
```

**Rate Limit:** 10 requests/minute

**Validaciones:**
- Usuario autenticado
- Firma válida (prueba de propiedad)
- Timestamp reciente (< 5 minutos)
- Wallet no usado por otro usuario

**Efectos:**
- Actualiza `users.wallet_address`
- Registra en `wallet_sync_log`

---

### 3. **Get Contract Data** (`get-contract-data.php`)

Lee datos de contratos inteligentes con cache (5 minutos).

**Método:** `GET`

**Parameters:**
- `type` - Tipo de dato (requerido)
- `address` - Dirección wallet (para balance, voting_power, delegates)
- `proposalId` - ID de propuesta (para proposal_state, proposal_votes)
- `chainId` - Chain ID (default: 0x89 = Polygon)

**Tipos soportados:**

#### Balance de tokens
```
GET /api/web3/get-contract-data.php?type=balance&address=0x...&chainId=0x89
```

**Response:**
```json
{
  "success": true,
  "data": {
    "address": "0x...",
    "balance": "1000000000000000000",
    "decimals": 18,
    "symbol": "GOV"
  },
  "cached": false,
  "timestamp": 1696723200,
  "chainId": "0x89"
}
```

#### Voting Power
```
GET /api/web3/get-contract-data.php?type=voting_power&address=0x...
```

**Response:**
```json
{
  "success": true,
  "data": {
    "address": "0x...",
    "votes": "500000000000000000",
    "decimals": 18
  },
  "cached": false
}
```

#### Delegatee
```
GET /api/web3/get-contract-data.php?type=delegates&address=0x...
```

**Response:**
```json
{
  "success": true,
  "data": {
    "address": "0x...",
    "delegatee": "0x..."
  }
}
```

#### Proposal State
```
GET /api/web3/get-contract-data.php?type=proposal_state&proposalId=123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "proposalId": "123",
    "state": 1,
    "stateName": "Active"
  }
}
```

**Estados:**
- 0: Pending
- 1: Active
- 2: Canceled
- 3: Defeated
- 4: Succeeded
- 5: Queued
- 6: Expired
- 7: Executed

#### Proposal Votes
```
GET /api/web3/get-contract-data.php?type=proposal_votes&proposalId=123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "proposalId": "123",
    "forVotes": "1000000000000000000",
    "againstVotes": "500000000000000000",
    "abstainVotes": "200000000000000000"
  }
}
```

**Rate Limit:** 30 requests/minute

**Cache:** 5 minutos (archivo JSON en `cache/web3/`)

---

## 🛠️ Web3 Helper (`web3-helper.php`)

Funciones utilitarias para operaciones Web3 en PHP.

### Funciones principales:

```php
// Validación
isValidEthAddress($address)           // Validar formato de dirección
toChecksumAddress($address)           // Normalizar a checksum

// Conversiones
weiToEther($wei, $decimals)          // Wei → Ether
etherToWei($ether, $decimals)        // Ether → Wei
hexToDec($hex)                       // Hex → Decimal

// Encoding
encodeFunctionCall($sig, $params)    // Codificar llamada a contrato

// Blockchain
makeRpcCall($url, $method, $params)  // Llamada RPC directa
callContractMethod($addr, $sig, $params, $chainId)  // Llamar método

// Helpers
getContractAddresses($chainId)       // Direcciones de contratos
getRpcUrlForChain($chainId)          // RPC URL por chain
getChainName($chainId)               // Nombre de chain
getExplorerUrl($chainId, $addr)      // URL de block explorer

// Contract Methods
getTokenBalance($token, $wallet, $chainId)      // Balance ERC20
getVotingPower($token, $wallet, $chainId)       // Poder de voto
getDelegatee($token, $wallet, $chainId)         // Delegado actual
```

---

## 🗄️ Base de Datos

### Tabla: `wallet_sync_log`

Registra intentos de sincronización de wallet.

```sql
CREATE TABLE wallet_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_address VARCHAR(42) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_wallet_address (wallet_address),
    INDEX idx_created_at (created_at)
);
```

**Migration:** `database/migrations/009_wallet_sync_log.sql`

---

## 🔒 Seguridad

### Rate Limiting
- verify-signature: 20/min
- sync-wallet: 10/min
- get-contract-data: 30/min

### Validaciones
- ✅ Formato de direcciones (checksummed)
- ✅ Formato de firmas (130 caracteres hex)
- ✅ Timestamp reciente (< 5 min)
- ✅ Sesión activa (sync-wallet)
- ✅ Unicidad de wallet por usuario

### CORS
- Permitido: `*` (configurar en producción)
- Métodos: GET, POST, OPTIONS
- Headers: Content-Type

---

## 📦 Dependencias

### Recomendado (Producción):
```bash
composer require web3p/web3.php
composer require kornrunner/keccak
composer require kornrunner/secp256k1
```

### Actual (Desarrollo):
- PHP nativo con `hash('sha3-256', ...)` (SHA3, no Keccak256 exacto)
- Placeholder para verificación de firmas

**⚠️ Nota:** Para verificación criptográfica completa, instalar Web3.php.

---

## 🚀 Setup

### 1. Crear directorio cache
```bash
mkdir cache/web3
chmod 777 cache/web3
```

### 2. Ejecutar migration
```bash
php database/run_migration.php 009
```

### 3. Configurar contract addresses

Editar `web3-helper.php`:
```php
function getContractAddresses($chainId) {
    return [
        '0x89' => [
            'governor' => '0xYourGovernorAddress',
            'token' => '0xYourTokenAddress',
            'timelock' => '0xYourTimelockAddress'
        ]
    ];
}
```

### 4. Test endpoints

**Verify signature:**
```bash
curl -X POST http://localhost/api/web3/verify-signature.php \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Test message",
    "signature": "0x...",
    "expectedSigner": "0x..."
  }'
```

**Get contract data:**
```bash
curl "http://localhost/api/web3/get-contract-data.php?type=balance&address=0x..."
```

---

## 📊 Monitoring

### Logs
```bash
# Ver logs de wallet sync
tail -f /var/log/php-error.log | grep "Web3"

# Ver intentos fallidos de sync
SELECT * FROM wallet_sync_log WHERE success = 0 ORDER BY created_at DESC LIMIT 10;

# Ver wallets sincronizadas hoy
SELECT COUNT(*) FROM wallet_sync_log WHERE DATE(created_at) = CURDATE() AND success = 1;
```

### Cache
```bash
# Ver archivos en cache
ls -lh cache/web3/

# Limpiar cache manualmente
rm cache/web3/*.json
```

---

## 🔧 Troubleshooting

### Error: "Failed to recover signer address"
- **Causa:** Web3.php no instalado o firma inválida
- **Solución:** Instalar `composer require web3p/web3.php`

### Error: "Signature timestamp is too old"
- **Causa:** Timestamp > 5 minutos
- **Solución:** Frontend debe generar nueva firma

### Error: "This wallet is already connected"
- **Causa:** Wallet usado por otro usuario
- **Solución:** Usuario debe desconectar wallet de otra cuenta primero

### Cache no expira
- **Causa:** Permisos de directorio
- **Solución:** `chmod 777 cache/web3/`

---

## 📝 TODO

- [ ] Instalar Web3.php para verificación completa de firmas
- [ ] Implementar lectura real de blockchain en get-contract-data
- [ ] Configurar CORS restrictivo para producción
- [ ] Agregar webhook para eventos on-chain
- [ ] Implementar Redis para cache distribuido
- [ ] Agregar métricas de uso de API
- [ ] Unit tests para verificación de firmas
- [ ] Documentación API con Swagger/OpenAPI

---

## 📚 Referencias

- [Web3.php Documentation](https://web3php.readthedocs.io/)
- [EIP-712: Typed structured data hashing](https://eips.ethereum.org/EIPS/eip-712)
- [MetaMask Docs: Signing Data](https://docs.metamask.io/wallet/how-to/sign-data/)
- [OpenZeppelin Governor](https://docs.openzeppelin.com/contracts/4.x/api/governance)
- [Polygon RPC Endpoints](https://docs.polygon.technology/pos/reference/rpc-endpoints/)

---

**Última actualización:** 2025-10-08  
**Estado:** ✅ Backend API Complete (Placeholder mode)
