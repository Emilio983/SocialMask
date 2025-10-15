import { RowDataPacket } from 'mysql2/promise';
import { ethers } from 'ethers';
import { pool } from '../db/index.js';
import { getSwapEventById, updateSwapExecutionDetails, updateSwapStatus } from '../services/swapEvents.js';
import { getRelayTaskStatus } from '../services/userOperation.js';
import { config } from '../config/index.js';
import { logger } from '../utils/logger.js';
import { getHttpProvider } from '../utils/web3.js';

async function fetchPendingSwaps(limit = 10) {
  const [rows] = await pool.execute<RowDataPacket[]>(
    'SELECT id, smart_account_address, aggregator, relay_task_id FROM swap_events WHERE status = "pending" ORDER BY created_at ASC LIMIT ?',
    [limit],
  );
  return rows;
}

async function checkSwapStatus(row: RowDataPacket) {
  const taskId = row.relay_task_id;
  if (!taskId) {
    logger.warn({ id: row.id }, 'Swap event without taskId');
    return;
  }

  const status = await getRelayTaskStatus(taskId);
  if (!status) {
    logger.warn({ id: row.id, taskId }, 'No status returned from Gelato');
    return;
  }

  const state = status.taskState;

  if (state === 'ExecPending' || state === 'CheckPending' || state === 'WaitingForConfirmation') {
    return;
  }

  if (state === 'ExecSuccess') {
    const transactionHash = status.transactionHash ?? undefined;
    await updateSwapStatus({
      eventId: row.id,
      status: 'executed',
      swapTxHash: transactionHash,
    });
    if (transactionHash) {
      try {
        await recalculateExecutedSwap(row.id, transactionHash, row.smart_account_address);
      } catch (error) {
        logger.error({ error, id: row.id, hash: transactionHash }, 'Failed to recalculate swap amounts');
      }
    }
    logger.info({ id: row.id, hash: transactionHash }, 'Swap executed successfully');
    return;
  }

  if (state === 'Cancelled') {
    await updateSwapStatus({ eventId: row.id, status: 'cancelled', failReason: 'Cancelled' });
    logger.warn({ id: row.id }, 'Swap cancelled');
    return;
  }

  if (state === 'ExecReverted') {
    await updateSwapStatus({ eventId: row.id, status: 'failed', failReason: state });
    logger.error({ id: row.id, state }, 'Swap failed');
  }
}

export async function startSwapMonitor(intervalMs = 30_000) {
  logger.info('Starting swap monitor loop');
  setInterval(async () => {
    try {
      const pending = await fetchPendingSwaps();
      for (const item of pending) {
        await checkSwapStatus(item);
      }
    } catch (error) {
      logger.error({ error }, 'swapMonitor iteration failed');
    }
  }, intervalMs);
}

const erc20Interface = new ethers.Interface(['event Transfer(address indexed from, address indexed to, uint256 value)']);

async function recalculateExecutedSwap(eventId: number, txHash: string, smartAccountAddress: string) {
  const swapEvent = await getSwapEventById(eventId);
  if (!swapEvent) {
    return;
  }

  const provider = getHttpProvider();
  const receipt = await provider.getTransactionReceipt(txHash);
  if (!receipt) {
    throw new Error('Missing transaction receipt for executed swap');
  }

  const spheAddress = config.tokens.sphe.address.toLowerCase();
  const smartAccount = ethers.getAddress(smartAccountAddress);
  let spheAmountRaw: bigint | null = null;

  for (const log of receipt.logs) {
    if ((log.address ?? '').toLowerCase() !== spheAddress) {
      continue;
    }
    try {
      const parsed = erc20Interface.parseLog(log);
      if (!parsed || !parsed.args || parsed.args.length < 3) {
        continue;
      }
      const to = ethers.getAddress(parsed.args[1]);
      if (to === smartAccount) {
        spheAmountRaw = BigInt(parsed.args[2]);
        break;
      }
    } catch (error) {
      // Ignore non-Transfer logs
    }
  }

  const usdAmount = parseFloat(swapEvent.amount_usdt);
  const usdRate = 1;
  const mxnRate = config.fxRateUsdMxn;
  const amountUsdEquiv = Number.isFinite(usdAmount) ? usdAmount : 0;
  const amountMxnEquiv = amountUsdEquiv * mxnRate;

  await updateSwapExecutionDetails({
    eventId,
    amountSphe: spheAmountRaw ? ethers.formatUnits(spheAmountRaw, config.tokens.sphe.decimals) : null,
    amountUsdEquiv,
    usdRate,
    mxnRate,
    amountMxnEquiv,
  });
}
