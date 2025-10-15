import { ResultSetHeader, RowDataPacket } from 'mysql2/promise';
import { pool } from '../db/index.js';
import { config } from '../config/index.js';

type SwapRecordInput = {
  userId: number;
  smartAccountAddress: string;
  aggregator: '0X' | 'QUICKSWAP' | 'UNISWAP';
  amountUsdt: string;
  amountSpheEstimate?: string;
  slippageBps: number;
  taskId: string;
  depositTxHash?: string;
  depositAddress?: string;
};

export async function recordSwapEvent(input: SwapRecordInput) {
  const amountSphe = input.amountSpheEstimate ?? '0';
  const amountUsdEquiv = parseFloat(input.amountUsdt);
  const usdRate = 1.0;
  const mxnRate = config.fxRateUsdMxn;
  const amountMxnEquiv = amountUsdEquiv * mxnRate;

  const [result] = await pool.execute<ResultSetHeader>(
    `INSERT INTO swap_events (
       user_id,
       smart_account_address,
       deposit_address,
       deposit_tx_hash,
       aggregator,
       status,
       amount_usdt,
       amount_sphe,
       usd_rate,
       mxn_rate,
       amount_usd_equiv,
       amount_mxn_equiv,
       slippage_bps,
       created_at
     )
     VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, NOW())`,
    [
      input.userId,
      input.smartAccountAddress,
      input.depositAddress ?? null,
      input.depositTxHash ?? null,
      input.aggregator,
      input.amountUsdt,
      amountSphe,
      usdRate,
      mxnRate,
      amountUsdEquiv,
      amountMxnEquiv,
      input.slippageBps,
    ],
  );

  const insertedId = result.insertId;
  await pool.execute(
    'UPDATE swap_events SET relay_task_id = ? WHERE id = ?',
    [input.taskId, insertedId],
  );

  return insertedId;
}

type UpdateSwapStatusInput = {
  eventId: number;
  status: 'executed' | 'failed' | 'cancelled';
  swapTxHash?: string;
  failReason?: string;
};

export async function updateSwapStatus({ eventId, status, swapTxHash, failReason }: UpdateSwapStatusInput) {
  await pool.execute(
    `UPDATE swap_events SET status = ?, swap_tx_hash = ?, fail_reason = ?, executed_at = CASE WHEN ? = 'executed' THEN NOW() ELSE executed_at END, updated_at = NOW()
     WHERE id = ?`,
    [status, swapTxHash ?? null, failReason ?? null, status, eventId],
  );
}

type SwapEventRow = {
  id: number;
  user_id: number;
  smart_account_address: string;
  amount_usdt: string;
  amount_sphe: string;
  usd_rate: number;
  mxn_rate: number;
  amount_usd_equiv: number;
  amount_mxn_equiv: number;
};

export async function getSwapEventById(eventId: number): Promise<SwapEventRow | null> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT id,
            user_id,
            smart_account_address,
            amount_usdt,
            amount_sphe,
            usd_rate,
            mxn_rate,
            amount_usd_equiv,
            amount_mxn_equiv
     FROM swap_events
     WHERE id = ?
     LIMIT 1`,
    [eventId],
  );

  if (!rows.length) {
    return null;
  }

  const row = rows[0];
  return {
    id: Number(row.id),
    user_id: Number(row.user_id),
    smart_account_address: String(row.smart_account_address),
    amount_usdt: String(row.amount_usdt ?? '0'),
    amount_sphe: String(row.amount_sphe ?? '0'),
    usd_rate: Number(row.usd_rate ?? 0),
    mxn_rate: Number(row.mxn_rate ?? 0),
    amount_usd_equiv: Number(row.amount_usd_equiv ?? 0),
    amount_mxn_equiv: Number(row.amount_mxn_equiv ?? 0),
  };
}

type SwapExecutionUpdate = {
  eventId: number;
  amountSphe?: string | null;
  amountUsdEquiv?: number | null;
  usdRate?: number | null;
  mxnRate?: number | null;
  amountMxnEquiv?: number | null;
};

export async function updateSwapExecutionDetails(update: SwapExecutionUpdate): Promise<void> {
  await pool.execute(
    `UPDATE swap_events
     SET amount_sphe = COALESCE(?, amount_sphe),
         amount_usd_equiv = COALESCE(?, amount_usd_equiv),
         usd_rate = COALESCE(?, usd_rate),
         mxn_rate = COALESCE(?, mxn_rate),
         amount_mxn_equiv = COALESCE(?, amount_mxn_equiv),
         updated_at = NOW()
     WHERE id = ?`,
    [
      update.amountSphe ?? null,
      update.amountUsdEquiv ?? null,
      update.usdRate ?? null,
      update.mxnRate ?? null,
      update.amountMxnEquiv ?? null,
      update.eventId,
    ],
  );
}
