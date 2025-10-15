# API de Staking - Documentación

## Descripción
APIs RESTful para el sistema de staking de tokens SPHE en Sphoria.

## Endpoints Disponibles

### 1. POST /api/staking/stake_tokens.php
Registra un nuevo depósito de staking.

**Request:**
```json
{
  "user_id": 1,
  "amount": "100.50",
  "pool_id": 0,
  "tx_hash": "0x1234..."
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Depósito registrado exitosamente",
  "data": {
    "deposit_id": 123,
    "user_id": 1,
    "amount": "100.50",
    "pool_id": 0,
    "pool_name": "Flexible",
    "tx_hash": "0x1234...",
    "staked_at": "2025-10-08 15:30:00",
    "stats": {
      "current_staked": "100.50",
      "total_staked": "100.50",
      "total_rewards_claimed": "0",
      "active_deposits_count": 1
    }
  }
}
```

---

### 2. POST /api/staking/unstake_tokens.php
Registra un retiro de tokens stakeados.

**Request:**
```json
{
  "user_id": 1,
  "amount": "50.00",
  "rewards": "2.50",
  "tx_hash": "0x5678...",
  "deposit_ids": [123, 124]
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Unstake registrado exitosamente",
  "data": {
    "user_id": 1,
    "amount_unstaked": "50.00",
    "rewards_claimed": "2.50",
    "tx_hash": "0x5678...",
    "unstaked_at": "2025-10-08 16:00:00",
    "stats": {
      "current_staked": "50.50",
      "total_unstaked": "50.00",
      "total_rewards_claimed": "2.50"
    }
  }
}
```

---

### 3. POST /api/staking/claim_rewards.php
Registra reclamación de rewards sin unstakear.

**Request:**
```json
{
  "user_id": 1,
  "amount": "5.25",
  "tx_hash": "0x9abc...",
  "deposit_id": 123
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Rewards reclamados exitosamente",
  "data": {
    "reward_id": 456,
    "user_id": 1,
    "amount": "5.25",
    "tx_hash": "0x9abc...",
    "claimed_at": "2025-10-08 16:30:00",
    "stats": {
      "current_staked": "100.50",
      "total_rewards_claimed": "7.75",
      "active_deposits_count": 1,
      "last_claim_at": "2025-10-08 16:30:00"
    }
  }
}
```

---

### 4. GET /api/staking/get_staking_info.php
Obtiene información completa de staking de un usuario.

**Query Params:**
- `user_id` (required): ID del usuario

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "username": "johndoe",
      "wallet_address": "0xabc..."
    },
    "summary": {
      "current_staked": "150.50",
      "total_staked": "200.00",
      "total_unstaked": "49.50",
      "total_rewards_claimed": "12.75",
      "active_deposits_count": 2,
      "total_deposits_count": 5,
      "weighted_apy": 15.5000,
      "first_stake_at": "2025-09-01 10:00:00",
      "last_stake_at": "2025-10-08 15:30:00",
      "last_claim_at": "2025-10-08 16:30:00"
    },
    "deposits_by_pool": [
      {
        "pool_id": 0,
        "pool_name": "Flexible",
        "lock_period_days": 0,
        "reward_multiplier": "1.00",
        "apy": "10.0000",
        "total_in_pool": "50.50",
        "deposits_count": 1,
        "first_deposit": "2025-09-01 10:00:00",
        "last_deposit": "2025-09-01 10:00:00"
      },
      {
        "pool_id": 1,
        "pool_name": "30 Days",
        "lock_period_days": 30,
        "reward_multiplier": "1.50",
        "apy": "15.0000",
        "total_in_pool": "100.00",
        "deposits_count": 1,
        "first_deposit": "2025-10-08 15:30:00",
        "last_deposit": "2025-10-08 15:30:00"
      }
    ],
    "active_deposits": [
      {
        "id": 123,
        "amount": "100.00",
        "pool_id": 1,
        "pool_name": "30 Days",
        "apy": "15.0000",
        "tx_hash": "0x1234...",
        "staked_at": "2025-10-08 15:30:00",
        "staking_duration_seconds": 3600,
        "unlock_date": "2025-11-07 15:30:00",
        "is_locked": true
      }
    ],
    "recent_rewards": [
      {
        "id": 456,
        "amount": "5.25",
        "reward_type": "claim",
        "tx_hash": "0x9abc...",
        "claimed_at": "2025-10-08 16:30:00"
      }
    ],
    "available_pools": [
      {
        "pool_id": 0,
        "name": "Flexible",
        "lock_period_days": 0,
        "reward_multiplier": "1.00",
        "min_stake": "10.00000000",
        "total_staked": "5000.50",
        "participants_count": 25,
        "apy": "10.0000",
        "is_active": true
      }
    ]
  }
}
```

---

### 5. GET /api/staking/get_staking_history.php
Obtiene historial de transacciones de staking.

**Query Params:**
- `user_id` (required): ID del usuario
- `type` (optional): Tipo de transacción (all, stake, unstake, claim)
- `pool_id` (optional): Filtrar por pool
- `limit` (optional): Límite de resultados (default: 50, max: 100)
- `offset` (optional): Offset para paginación (default: 0)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "transactions": [
      {
        "id": 789,
        "transaction_type": "stake",
        "amount": "100.00",
        "pool_id": 1,
        "pool_name": "30 Days",
        "tx_hash": "0x1234...",
        "gas_used": null,
        "status": "confirmed",
        "error_message": null,
        "created_at": "2025-10-08 15:30:00",
        "confirmed_at": "2025-10-08 15:30:00",
        "type_label": "Depósito de Staking"
      }
    ],
    "pagination": {
      "total": 25,
      "limit": 10,
      "offset": 0,
      "has_more": true,
      "current_page": 1,
      "total_pages": 3
    },
    "stats_by_type": {
      "stake": {
        "count": 10,
        "total_amount": "500.00"
      },
      "unstake": {
        "count": 5,
        "total_amount": "200.00"
      },
      "claim": {
        "count": 10,
        "total_amount": "25.50"
      }
    },
    "recent_activity": [
      {
        "date": "2025-10-08",
        "transaction_type": "stake",
        "count": 2,
        "total_amount": "150.00"
      }
    ]
  }
}
```

---

### 6. GET /api/staking/get_staking_stats.php
Obtiene estadísticas globales del sistema.

**Query Params:**
- `user_id` (optional): Incluir estadísticas del usuario

**Response (200):**
```json
{
  "success": true,
  "data": {
    "global": {
      "total_value_locked": "125000.50",
      "total_participants": 150,
      "total_rewards_distributed": "5000.25",
      "weighted_system_apy": 14.5000,
      "active_pools_count": 4
    },
    "pools": [
      {
        "pool_id": 0,
        "name": "Flexible",
        "lock_period_days": 0,
        "reward_multiplier": "1.00",
        "min_stake": "10.00000000",
        "apy": "10.0000",
        "total_staked": "25000.00",
        "participants_count": 50,
        "is_active": true,
        "pool_percentage": 20.00
      }
    ],
    "top_stakers": [
      {
        "id": 5,
        "username": "whale1",
        "wallet_address": "0xdef...",
        "current_staked": "10000.00",
        "total_rewards_claimed": "500.00",
        "active_deposits_count": 3,
        "ranking": 1
      }
    ],
    "activity_7days": [
      {
        "date": "2025-10-08",
        "transactions_count": 15,
        "stakes": "5000.00",
        "unstakes": "1500.00",
        "claims": "250.00"
      }
    ],
    "tvl_growth_30days": [
      {
        "date": "2025-10-08",
        "daily_stakes": "5000.00"
      }
    ],
    "rewards_by_month": [
      {
        "month": "2025-10",
        "claims_count": 150,
        "total_rewards": "2000.00"
      }
    ],
    "user_stats": {
      "user_id": 1,
      "current_staked": "150.50",
      "total_staked": "200.00",
      "total_rewards_claimed": "12.75",
      "user_ranking": 45,
      "user_tvl_percentage": 0.1204
    }
  },
  "metadata": {
    "generated_at": "2025-10-08 17:00:00",
    "cache_duration": 300
  }
}
```

---

## Códigos de Error

- **400**: Bad Request - Datos faltantes o inválidos
- **401**: Unauthorized - No autenticado
- **403**: Forbidden - Sin permisos
- **404**: Not Found - Recurso no encontrado
- **405**: Method Not Allowed - Método HTTP incorrecto
- **500**: Internal Server Error - Error del servidor

## Formato de Errores

```json
{
  "success": false,
  "message": "Descripción del error"
}
```

## Testing

### Bash (Linux/Mac)
```bash
chmod +x test_staking_api.sh
./test_staking_api.sh
```

### PowerShell (Windows)
```powershell
.\test_staking_api.ps1
```

## Notas Importantes

1. **Validación de TX Hash**: Debe ser formato válido: `0x` + 64 caracteres hexadecimales
2. **Pool IDs**: 0-3 son los pools válidos por defecto
3. **Montos**: Se manejan como decimales de 8 dígitos
4. **Paginación**: Máximo 100 resultados por petición
5. **Cache**: Las estadísticas globales se cachean por 5 minutos

## Base de Datos

### Tablas Principales
- `staking_deposits`: Depósitos de staking
- `staking_rewards`: Rewards reclamadas
- `staking_stats`: Estadísticas por usuario
- `staking_pools_info`: Información de pools
- `staking_transactions_log`: Log de transacciones

### Triggers Automáticos
- Actualización automática de stats después de insert/update
- Actualización de pool stats
- Cálculo de totales acumulados

## Soporte

Para reportar bugs o solicitar features, contactar al equipo de desarrollo.
