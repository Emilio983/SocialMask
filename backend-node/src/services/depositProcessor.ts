import { RowDataPacket } from 'mysql2/promise';
import { ethers } from 'ethers';
import { pool } from '../db/index.js';
import { logger } from '../utils/logger.js';
import { executeSwapWithFallback } from './swap.js';
import { config } from '../config/index.js';
import { submitRotatingSweep } from './rotatingSweep.js';
import { getHttpProvider } from '../utils/web3.js';

type DepositSource =
  | { source: 'smart_account'; userId: number; smartAccountAddress: string }
  | {
      source: 'rotating';
      userId: number;
      smartAccountAddress: string;
      rotatingId: number;
      metadataSalt: string;
      depositAddress: string;
    };

const smartAccountCache = new Map<string, DepositSource | null>();

async function lookupDepositTarget(address: string): Promise<DepositSource | null> {
  const normalized = ethers.getAddress(address);
  const lower = normalized.toLowerCase();

  if (smartAccountCache.has(lower)) {
    return smartAccountCache.get(lower) ?? null;
  }

  const [smartRows] = await pool.execute<RowDataPacket[]>(
    'SELECT sa.user_id, sa.smart_account_address FROM smart_accounts sa WHERE LOWER(sa.smart_account_address) = ? LIMIT 1',
    [lower],
  );

  if (smartRows.length > 0) {
    const record = {
      source: 'smart_account' as const,
      userId: Number(smartRows[0].user_id),
      smartAccountAddress: ethers.getAddress(smartRows[0].smart_account_address),
    };
    smartAccountCache.set(lower, record);
    return record;
  }

  const [rotateRows] = await pool.execute<RowDataPacket[]>(
    'SELECT id, user_id, smart_account_address, metadata_hash, deposit_address FROM rotating_addresses WHERE LOWER(deposit_address) = ? LIMIT 1',
    [lower],
  );

  if (rotateRows.length > 0) {
    const record = {
      source: 'rotating' as const,
      userId: Number(rotateRows[0].user_id),
      smartAccountAddress: ethers.getAddress(rotateRows[0].smart_account_address),
      rotatingId: Number(rotateRows[0].id),
      metadataSalt: String(rotateRows[0].metadata_hash ?? ''),
      depositAddress: ethers.getAddress(rotateRows[0].deposit_address),
    };
    smartAccountCache.set(lower, record);
    return record;
  }

  smartAccountCache.set(lower, null);
  return null;
}

async function markRotatingAddressUsed(rotatingId: number, txHash: string) {
  await pool.execute(
    `UPDATE rotating_addresses
     SET status = 'used',
         used_at = NOW(),
         deposit_tx_hash = ?
     WHERE id = ?`,
    [txHash, rotatingId],
  );
}

async function hasProcessedDeposit(txHash: string): Promise<boolean> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    'SELECT id FROM swap_events WHERE deposit_tx_hash = ? LIMIT 1',
    [txHash],
  );
  return rows.length > 0;
}

export async function processUsdtDeposit(params: {
  from: string;
  to: string;
  value: bigint;
  txHash: string;
  blockNumber: number;
}) {
  const { from, to, value, txHash, blockNumber } = params;

  if (value <= 0n) {
    return;
  }

  const target = await lookupDepositTarget(to);
  if (!target) {
    return;
  }

  if (await hasProcessedDeposit(txHash)) {
    logger.debug({ txHash }, 'Deposit already processed, skipping');
    return;
  }

  // Evitar ciclos si la transferencia proviene del propio smart account
  if (ethers.getAddress(from) === target.smartAccountAddress) {
    return;
  }

  if ('rotatingId' in target) {
    await markRotatingAddressUsed(target.rotatingId, txHash);
    try {
      await submitRotatingSweep({
        rotatingAddressId: target.rotatingId,
        metadataSalt: target.metadataSalt,
        amount: value,
        smartAccountAddress: target.smartAccountAddress,
        depositAddress: target.depositAddress,
        depositTxHash: txHash,
        userId: target.userId,
        blockNumber,
      });
    } catch (error) {
      logger.error(
        { error, rotatingId: target.rotatingId, txHash, smartAccount: target.smartAccountAddress },
        'Failed to sweep rotating deposit',
      );
    }
    return;
  }

  const amountRaw = value.toString();
  const usdEstimate = Number(ethers.formatUnits(value, config.tokens.usdt.decimals));

  logger.info(
    {
      userId: target.userId,
      smartAccount: target.smartAccountAddress,
      amountRaw,
      txHash,
      blockNumber,
    },
    'Auto-swap triggered from USDT deposit',
  );

  setTimeout(async () => {
    try {
      await executeSwapWithFallback({
        smartAccountAddress: target.smartAccountAddress,
        userId: target.userId,
        amountRaw,
        usdEstimate,
        depositTxHash: txHash,
        depositAddress: ethers.getAddress(to),
      });
    } catch (error) {
      logger.error(
        { error, txHash, smartAccount: target.smartAccountAddress },
        'Failed to execute auto-swap after deposit',
      );
    }
  }, 5_000);
}
