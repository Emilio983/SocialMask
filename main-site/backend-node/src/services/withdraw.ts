import { ResultSetHeader, RowDataPacket } from 'mysql2/promise';
import { pool } from '../db/index.js';
import { config } from '../config/index.js';
import { logger } from '../utils/logger.js';
import { ethers } from 'ethers';
import { AppError } from '../utils/errors.js';

type WithdrawRecordInput = {
  userId: number;
  smartAccountAddress: string;
  destinationAddress: string;
  aggregator: '0X' | 'QUICKSWAP' | 'UNISWAP';
  amountSphe: string;
  amountUsdtEstimate?: string;
  slippageBps: number;
  taskId: string;
};

export async function recordWithdrawEvent(input: WithdrawRecordInput) {
  const amountUsdt = input.amountUsdtEstimate ?? '0';
  const amountUsdEquiv = parseFloat(amountUsdt);
  const usdRate = 1.0;
  const mxnRate = config.fxRateUsdMxn;
  const amountMxnEquiv = amountUsdEquiv * mxnRate;

  const [result] = await pool.execute<ResultSetHeader>(
    `INSERT INTO withdraw_events (
      user_id, smart_account_address, destination_address, aggregator,
      status, amount_sphe, amount_usdt, usd_rate, mxn_rate,
      amount_usd_equiv, amount_mxn_equiv, slippage_bps, created_at
    ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, NOW())`,
    [
      input.userId,
      input.smartAccountAddress,
      input.destinationAddress,
      input.aggregator,
      input.amountSphe,
      amountUsdt,
      usdRate,
      mxnRate,
      amountUsdEquiv,
      amountMxnEquiv,
      input.slippageBps,
    ],
  );

  const insertedId = result.insertId;
  await pool.execute(
    'UPDATE withdraw_events SET relay_task_id = ? WHERE id = ?',
    [input.taskId, insertedId],
  );

  return insertedId;
}

type UpdateWithdrawStatusInput = {
  eventId: number;
  status: 'executed' | 'failed' | 'cancelled';
  withdrawTxHash?: string;
  failReason?: string;
};

export async function updateWithdrawStatus({
  eventId,
  status,
  withdrawTxHash,
  failReason
}: UpdateWithdrawStatusInput) {
  await pool.execute(
    `UPDATE withdraw_events
     SET status = ?, withdraw_tx_hash = ?, fail_reason = ?,
         executed_at = CASE WHEN ? = 'executed' THEN NOW() ELSE executed_at END,
         updated_at = NOW()
     WHERE id = ?`,
    [status, withdrawTxHash ?? null, failReason ?? null, status, eventId],
  );
}

export async function validateWithdrawAddress(address: string): Promise<boolean> {
  try {
    const isValid = ethers.isAddress(address);

    // Verificar que no sea dirección 0x0
    if (isValid && address.toLowerCase() === '0x0000000000000000000000000000000000000000') {
      return false;
    }

    return isValid;
  } catch (error) {
    return false;
  }
}

export async function getUserWithdrawHistory(
  userId: number,
  limit: number = 10
): Promise<RowDataPacket[]> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT id, destination_address, amount_sphe, amount_usdt, status,
            withdraw_tx_hash, fail_reason, created_at, executed_at
     FROM withdraw_events
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT ?`,
    [userId, limit]
  );

  return rows;
}

export async function getPendingWithdraws(limit: number = 10): Promise<RowDataPacket[]> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT id, smart_account_address, aggregator, relay_task_id
     FROM withdraw_events
     WHERE status = 'pending'
     ORDER BY created_at ASC
     LIMIT ?`,
    [limit]
  );

  return rows;
}
