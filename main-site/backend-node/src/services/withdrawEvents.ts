import { pool } from '../db/index.js';
import { ethers } from 'ethers';
import { logger } from '../utils/logger.js';

export interface WithdrawEvent {
  id?: number;
  user_id: number;
  smart_account_address: string;
  external_address: string;
  amount_sphe_wei: bigint;
  amount_usdt_wei: bigint;
  swap_task_id: string | null;
  transfer_task_id: string;
  status: 'pending' | 'executed' | 'failed' | 'cancelled';
  tx_hash?: string | null;
  fail_reason?: string | null;
  executed_at?: Date | null;
  created_at?: Date;
  updated_at?: Date;
}

/**
 * Registra un nuevo evento de retiro en la base de datos
 */
export async function recordWithdrawEvent(event: Omit<WithdrawEvent, 'id' | 'created_at' | 'updated_at'>): Promise<number> {
  const sql = `
    INSERT INTO withdraw_events (
      user_id,
      smart_account_address,
      external_address,
      amount_sphe_wei,
      amount_usdt_wei,
      swap_task_id,
      transfer_task_id,
      status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  `;

  const values = [
    event.user_id,
    event.smart_account_address,
    event.external_address,
    event.amount_sphe_wei.toString(),
    event.amount_usdt_wei.toString(),
    event.swap_task_id,
    event.transfer_task_id,
    event.status,
  ];

  try {
    const [result]: any = await pool.execute(sql, values);
    logger.info({ withdrawId: result.insertId, userId: event.user_id }, 'Withdraw event recorded');
    return result.insertId;
  } catch (error) {
    logger.error({ err: error, event }, 'Error recording withdraw event');
    throw error;
  }
}

/**
 * Actualiza el estado de un retiro
 */
export async function updateWithdrawStatus(
  withdrawId: number,
  status: 'executed' | 'failed' | 'cancelled',
  txHash?: string | null,
  failReason?: string | null
): Promise<void> {
  const sql = `
    UPDATE withdraw_events
    SET status = ?,
        tx_hash = ?,
        fail_reason = ?,
        executed_at = CASE WHEN ? = 'executed' THEN NOW() ELSE executed_at END,
        updated_at = NOW()
    WHERE id = ?
  `;

  try {
    await pool.execute(sql, [status, txHash || null, failReason || null, status, withdrawId]);
    logger.info({ withdrawId, status, txHash }, 'Withdraw status updated');
  } catch (error) {
    logger.error({ err: error, withdrawId, status }, 'Error updating withdraw status');
    throw error;
  }
}

/**
 * Obtiene un retiro por ID
 */
export async function getWithdrawById(withdrawId: number): Promise<WithdrawEvent | null> {
  const sql = 'SELECT * FROM withdraw_events WHERE id = ?';
  
  try {
    const [rows]: any = await pool.execute(sql, [withdrawId]);
    if (rows.length === 0) return null;

    const row = rows[0];
    return {
      ...row,
      amount_sphe_wei: BigInt(row.amount_sphe_wei),
      amount_usdt_wei: BigInt(row.amount_usdt_wei),
    };
  } catch (error) {
    logger.error({ err: error, withdrawId }, 'Error fetching withdraw by ID');
    throw error;
  }
}

/**
 * Obtiene el historial de retiros del usuario
 * @param userId - ID del usuario
 * @param hoursWindow - Ventana de tiempo en horas para calcular totales (default: 24h)
 * @param page - Página actual (default: 1)
 * @param limit - Límite por página (default: 20)
 */
export async function getWithdrawHistory(
  userId: number,
  hoursWindow: number = 24,
  page: number = 1,
  limit: number = 20
): Promise<{
  withdraws: WithdrawEvent[];
  total: number;
  totalUsdToday: number;
}> {
  try {
    // 1. Contar total de retiros del usuario
    const countSql = 'SELECT COUNT(*) as total FROM withdraw_events WHERE user_id = ?';
    const [countRows]: any = await pool.execute(countSql, [userId]);
    const total = countRows[0].total;

    // 2. Calcular total en USD de las últimas N horas
    const totalSql = `
      SELECT COALESCE(SUM(amount_usdt_wei), 0) as total_usdt_wei
      FROM withdraw_events
      WHERE user_id = ?
        AND status IN ('pending', 'executed')
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    `;
    const [totalRows]: any = await pool.execute(totalSql, [userId, hoursWindow]);
    const totalUsdtWei = BigInt(totalRows[0].total_usdt_wei);
    const totalUsdToday = parseFloat(ethers.formatUnits(totalUsdtWei, 6)); // USDT tiene 6 decimales

    // 3. Obtener retiros paginados
    const offset = (page - 1) * limit;
    const withdrawsSql = `
      SELECT *
      FROM withdraw_events
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT ? OFFSET ?
    `;
    const [rows]: any = await pool.execute(withdrawsSql, [userId, limit, offset]);

    const withdraws: WithdrawEvent[] = rows.map((row: any) => ({
      ...row,
      amount_sphe_wei: BigInt(row.amount_sphe_wei),
      amount_usdt_wei: BigInt(row.amount_usdt_wei),
    }));

    return {
      withdraws,
      total,
      totalUsdToday,
    };
  } catch (error) {
    logger.error({ err: error, userId }, 'Error fetching withdraw history');
    throw error;
  }
}

/**
 * Obtiene retiros pendientes para monitoreo
 */
export async function getPendingWithdraws(): Promise<WithdrawEvent[]> {
  const sql = `
    SELECT *
    FROM withdraw_events
    WHERE status = 'pending'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY created_at ASC
  `;

  try {
    const [rows]: any = await pool.execute(sql);
    return rows.map((row: any) => ({
      ...row,
      amount_sphe_wei: BigInt(row.amount_sphe_wei),
      amount_usdt_wei: BigInt(row.amount_usdt_wei),
    }));
  } catch (error) {
    logger.error({ err: error }, 'Error fetching pending withdraws');
    throw error;
  }
}

/**
 * Obtiene el uso de límite diario de retiros para un usuario
 * Ventana deslizante de 24 horas
 */
export async function getDailyWithdrawLimits(userId: number): Promise<{
  dailyLimitUsd: number;
  usedTodayUsd: number;
  remainingUsd: number;
  percentageUsed: number;
}> {
  const DAILY_LIMIT_USD = 1000; // $1,000 USD por día
  const SPHE_PRICE_USD = 0.10; // $0.10 por SPHE (debería venir de oráculo)

  const sql = `
    SELECT SUM(amount_sphe_wei) as total_sphe_wei
    FROM withdraw_events
    WHERE user_id = ?
      AND status IN ('pending', 'executed')
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  `;

  try {
    const [rows]: any = await pool.execute(sql, [userId]);
    const totalSpheWei = rows[0]?.total_sphe_wei || '0';
    const totalSphe = parseFloat(ethers.formatUnits(BigInt(totalSpheWei), 18));
    const usedTodayUsd = totalSphe * SPHE_PRICE_USD;
    const remainingUsd = Math.max(0, DAILY_LIMIT_USD - usedTodayUsd);
    const percentageUsed = (usedTodayUsd / DAILY_LIMIT_USD) * 100;

    return {
      dailyLimitUsd: DAILY_LIMIT_USD,
      usedTodayUsd,
      remainingUsd,
      percentageUsed,
    };
  } catch (error) {
    logger.error({ err: error, userId }, 'Error calculating daily withdraw limits');
    throw error;
  }
}

/**
 * Valida que una dirección Ethereum sea válida
 */
export function validateWithdrawAddress(address: string): boolean {
  return ethers.isAddress(address);
}
