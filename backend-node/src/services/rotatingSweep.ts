import { ResultSetHeader, RowDataPacket } from 'mysql2/promise';
import { ethers } from 'ethers';
import { pool } from '../db/index.js';
import { deriveDepositPrivateKey } from './addressRotation.js';
import { submitSponsoredCallERC2771 } from './userOperation.js';
import { getHttpProvider } from '../utils/web3.js';
import { logger } from '../utils/logger.js';
import { config } from '../config/index.js';

const erc20Abi = [
  'event Transfer(address indexed from, address indexed to, uint256 value)',
  'function transfer(address to, uint256 amount) returns (bool)',
];

const erc20Interface = new ethers.Interface(erc20Abi);

type RecordSweepTaskInput = {
  rotatingAddressId: number;
  taskId: string;
};

export async function recordSweepTask({ rotatingAddressId, taskId }: RecordSweepTaskInput) {
  await pool.execute<ResultSetHeader>(
    `INSERT INTO rotating_sweep_tasks (rotating_address_id, task_id, status, created_at)
     VALUES (?, ?, 'pending', NOW())
     ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()`,
    [rotatingAddressId, taskId],
  );
}

type UpdateSweepStatusInput = {
  taskId: string;
  status: 'executed' | 'failed' | 'cancelled';
  failReason?: string;
};

export async function updateSweepStatus({ taskId, status, failReason }: UpdateSweepStatusInput) {
  await pool.execute(
    `UPDATE rotating_sweep_tasks
     SET status = ?, fail_reason = ?, completed_at = CASE WHEN ? = 'executed' THEN NOW() ELSE completed_at END, updated_at = NOW()
     WHERE task_id = ?`,
    [status, failReason ?? null, status, taskId],
  );
}

export async function fetchPendingSweeps(limit = 20) {
  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT id, rotating_address_id, task_id
     FROM rotating_sweep_tasks
     WHERE status = 'pending'
     ORDER BY created_at ASC
     LIMIT ?`,
    [limit],
  );
  return rows;
}

type RotatingAddressContext = {
  rotatingAddressId: number;
  userId: number;
  smartAccountAddress: string;
  metadataSalt: string;
  depositAddress: string;
  depositTxHash: string | null;
};

async function getRotatingAddressContext(rotatingAddressId: number): Promise<RotatingAddressContext | null> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT id, user_id, smart_account_address, metadata_hash, deposit_address, deposit_tx_hash
     FROM rotating_addresses
     WHERE id = ?
     LIMIT 1`,
    [rotatingAddressId],
  );

  if (!rows.length) {
    return null;
  }

  const row = rows[0];
  return {
    rotatingAddressId,
    userId: Number(row.user_id),
    smartAccountAddress: ethers.getAddress(row.smart_account_address),
    metadataSalt: String(row.metadata_hash ?? ''),
    depositAddress: ethers.getAddress(row.deposit_address),
    depositTxHash: row.deposit_tx_hash ?? null,
  };
}

async function getDepositAmountFromTx(depositTxHash: string, depositAddress: string): Promise<bigint> {
  const provider = getHttpProvider();
  const receipt = await provider.getTransactionReceipt(depositTxHash);
  if (!receipt) {
    throw new Error(`Missing receipt for deposit tx ${depositTxHash}`);
  }

  const targetAddress = ethers.getAddress(depositAddress);
  const usdtAddress = ethers.getAddress(config.tokens.usdt.address);

  for (const log of receipt.logs) {
    if ((log.address ?? '').toLowerCase() !== usdtAddress.toLowerCase()) {
      continue;
    }

    try {
      const parsed = erc20Interface.parseLog(log);
      if (!parsed || !parsed.args || parsed.args.length < 3) {
        continue;
      }
      const to = ethers.getAddress(parsed.args[1]);
      if (to === targetAddress) {
        return BigInt(parsed.args[2]);
      }
    } catch (error) {
      // ignore unrelated logs
    }
  }

  return 0n;
}

export async function countSweepAttempts(rotatingAddressId: number): Promise<number> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    'SELECT COUNT(*) AS total FROM rotating_sweep_tasks WHERE rotating_address_id = ?',
    [rotatingAddressId],
  );
  return rows.length ? Number(rows[0].total ?? 0) : 0;
}

type SubmitRotatingSweepInput = {
  rotatingAddressId: number;
  metadataSalt: string;
  smartAccountAddress: string;
  depositAddress: string;
  amount: bigint;
  depositTxHash: string;
  userId: number;
  blockNumber?: number;
};

export async function submitRotatingSweep(input: SubmitRotatingSweepInput): Promise<string> {
  if (!input.metadataSalt) {
    throw new Error('Missing metadata salt for rotating sweep');
  }

  const provider = getHttpProvider();
  const privateKey = deriveDepositPrivateKey(input.smartAccountAddress, input.metadataSalt);
  const wallet = new ethers.Wallet(privateKey, provider);

  const data = erc20Interface.encodeFunctionData('transfer', [input.smartAccountAddress, input.amount]);

  const response = await submitSponsoredCallERC2771({
    wallet,
    target: config.tokens.usdt.address,
    data,
    userDeadlineSeconds: 3600,
  });

  await recordSweepTask({ rotatingAddressId: input.rotatingAddressId, taskId: response.taskId });

  logger.info(
    {
      rotatingAddressId: input.rotatingAddressId,
      taskId: response.taskId,
      userId: input.userId,
      depositTxHash: input.depositTxHash,
      blockNumber: input.blockNumber,
    },
    'Rotating sweep task submitted to Gelato',
  );

  return response.taskId;
}

export async function retryRotatingSweep(rotatingAddressId: number): Promise<string | null> {
  const attempts = await countSweepAttempts(rotatingAddressId);
  if (attempts >= 3) {
    logger.warn({ rotatingAddressId, attempts }, 'Maximum rotating sweep retries reached');
    return null;
  }

  const context = await getRotatingAddressContext(rotatingAddressId);
  if (!context) {
    logger.error({ rotatingAddressId }, 'Unable to load rotating address context for retry');
    return null;
  }

  if (!context.depositTxHash) {
    logger.error({ rotatingAddressId }, 'Cannot retry rotating sweep without deposit tx hash');
    return null;
  }

  const amount = await getDepositAmountFromTx(context.depositTxHash, context.depositAddress);
  if (amount <= 0n) {
    logger.error({ rotatingAddressId, depositTxHash: context.depositTxHash }, 'Failed to derive deposit amount for rotating sweep retry');
    return null;
  }

  const taskId = await submitRotatingSweep({
    rotatingAddressId,
    metadataSalt: context.metadataSalt,
    smartAccountAddress: context.smartAccountAddress,
    depositAddress: context.depositAddress,
    amount,
    depositTxHash: context.depositTxHash,
    userId: context.userId,
  });

  logger.info({ rotatingAddressId, taskId }, 'Rotating sweep retry submitted');
  return taskId;
}
