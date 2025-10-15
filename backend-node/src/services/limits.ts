import { ResultSetHeader, RowDataPacket } from 'mysql2/promise';
import { pool } from '../db/index.js';
import { config } from '../config/index.js';
import { logger } from '../utils/logger.js';

type LimitCheckResult = {
  allowed: boolean;
  remainingUsd: number;
  remainingTxs: number;
  message?: string;
};

export async function checkGasSponsorshipLimits(userId: number): Promise<LimitCheckResult> {
  logger.debug({ userId }, 'checkGasSponsorshipLimits invoked');

  const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD

  // Buscar registro del día actual
  const [rows] = await pool.execute<RowDataPacket[]>(
    'SELECT tx_count, total_usd FROM gas_sponsorship_usage WHERE user_id = ? AND usage_date = ?',
    [userId, today]
  );

  const currentTxCount = rows.length > 0 ? rows[0].tx_count : 0;
  const currentTotalUsd = rows.length > 0 ? parseFloat(rows[0].total_usd) : 0;

  const remainingTxs = config.gelato.txsPerDayLimit - currentTxCount;
  const remainingUsd = config.gelato.gasSponsorLimitUsd - currentTotalUsd;

  const allowed = remainingTxs > 0 && remainingUsd > 0;

  let message = '';
  if (!allowed) {
    if (remainingTxs <= 0) {
      message = `Daily transaction limit reached (${config.gelato.txsPerDayLimit} txs/day)`;
    } else if (remainingUsd <= 0) {
      message = `Daily USD limit reached ($${config.gelato.gasSponsorLimitUsd}/day)`;
    }
  }

  return {
    allowed,
    remainingUsd: Math.max(0, remainingUsd),
    remainingTxs: Math.max(0, remainingTxs),
    message
  };
}

export async function registerSponsoredTx(userId: number, usdEstimate: number): Promise<void> {
  logger.info({ userId, usdEstimate }, 'registerSponsoredTx invoked');

  const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD

  // Usar INSERT ON DUPLICATE KEY UPDATE para crear o actualizar
  await pool.execute(
    `INSERT INTO gas_sponsorship_usage (user_id, usage_date, tx_count, total_usd, last_tx_at)
     VALUES (?, ?, 1, ?, NOW())
     ON DUPLICATE KEY UPDATE
       tx_count = tx_count + 1,
       total_usd = total_usd + VALUES(total_usd),
       last_tx_at = NOW(),
       updated_at = NOW()`,
    [userId, today, usdEstimate]
  );

  logger.info({ userId, usdEstimate }, 'Sponsored tx registered successfully');
}

export async function getUserDailyUsage(userId: number): Promise<{ txCount: number; totalUsd: number }> {
  const today = new Date().toISOString().split('T')[0];

  const [rows] = await pool.execute<RowDataPacket[]>(
    'SELECT tx_count, total_usd FROM gas_sponsorship_usage WHERE user_id = ? AND usage_date = ?',
    [userId, today]
  );

  if (rows.length === 0) {
    return { txCount: 0, totalUsd: 0 };
  }

  return {
    txCount: rows[0].tx_count,
    totalUsd: parseFloat(rows[0].total_usd)
  };
}
