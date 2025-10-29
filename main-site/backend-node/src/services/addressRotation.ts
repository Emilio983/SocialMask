import { randomBytes, createHmac } from 'crypto';
import { ResultSetHeader, RowDataPacket } from 'mysql2/promise';
import { ethers } from 'ethers';
import { pool } from '../db/index.js';
import { config } from '../config/index.js';
import { logger } from '../utils/logger.js';
import { ValidationError } from '../utils/errors.js';

export type RotatedAddress = {
  depositAddress: string;
  smartAccountAddress: string;
  expiresAt: Date | null;
};

const ROTATION_TTL_MS = 24 * 60 * 60 * 1000; // 24 horas

function formatMysqlDate(date: Date | null): string | null {
  if (!date) {
    return null;
  }
  return date.toISOString().slice(0, 19).replace('T', ' ');
}

export function deriveDepositPrivateKey(smartAccountAddress: string, salt: string): string {
  const hmac = createHmac('sha256', config.rotatingAddresses.derivationSecret);
  hmac.update(smartAccountAddress.toLowerCase());
  hmac.update(':');
  hmac.update(salt);
  return hmac.digest('hex');
}

export async function generateRotatedAddress(
  smartAccountAddress: string,
  options: { forceNew?: boolean } = {},
): Promise<RotatedAddress> {
  if (!smartAccountAddress) {
    throw new ValidationError('smartAccountAddress required');
  }

  const normalized = ethers.getAddress(smartAccountAddress);
  logger.debug({ smartAccountAddress: normalized }, 'generateRotatedAddress invoked');

  const [accountRows] = await pool.execute<RowDataPacket[]>(
    'SELECT user_id FROM smart_accounts WHERE LOWER(smart_account_address) = ? LIMIT 1',
    [normalized.toLowerCase()],
  );

  if (!accountRows.length) {
    throw new ValidationError('Smart account not registered');
  }

  const userId = Number(accountRows[0].user_id);
  const smartAccountLower = normalized.toLowerCase();

  // Expire addresses whose TTL already elapsed
  await pool.execute(
    `UPDATE rotating_addresses
     SET status = 'expired', updated_at = NOW()
     WHERE user_id = ? AND smart_account_address = ? AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW()`,
    [userId, smartAccountLower],
  );

  const [existingRows] = await pool.execute<RowDataPacket[]>(
    `SELECT deposit_address, metadata_hash, expires_at
     FROM rotating_addresses
     WHERE user_id = ? AND status = 'active' AND smart_account_address = ? AND (expires_at IS NULL OR expires_at > NOW())
     ORDER BY created_at DESC
     LIMIT 1`,
    [userId, smartAccountLower],
  );

  if (!options.forceNew && existingRows.length > 0) {
    const current = existingRows[0];
    return {
      depositAddress: ethers.getAddress(current.deposit_address),
      smartAccountAddress: normalized,
      expiresAt: current.expires_at ? new Date(current.expires_at) : null,
    };
  }

  const salt = randomBytes(32).toString('hex');
  const privateKey = deriveDepositPrivateKey(normalized, salt);
  const wallet = new ethers.Wallet(privateKey);
  const depositAddress = wallet.address;
  const expiresAt = ROTATION_TTL_MS > 0 ? new Date(Date.now() + ROTATION_TTL_MS) : null;

  const [result] = await pool.execute<ResultSetHeader>(
    `INSERT INTO rotating_addresses (
       user_id,
       smart_account_address,
       deposit_address,
       status,
       metadata_hash,
       created_at,
       expires_at,
       used_at,
       deposit_tx_hash
     ) VALUES (?, ?, ?, 'active', ?, NOW(), ?, NULL, NULL)`,
    [
      userId,
      smartAccountLower,
      depositAddress.toLowerCase(),
      salt,
      formatMysqlDate(expiresAt),
    ],
  );

  logger.info({ rotatingId: result.insertId, userId, depositAddress }, 'Rotating deposit address created');

  return {
    depositAddress,
    smartAccountAddress: normalized,
    expiresAt,
  };
}

export async function resolveRotatedAddress(depositAddress: string): Promise<string | null> {
  if (!depositAddress) {
    return null;
  }

  const normalized = ethers.getAddress(depositAddress);
  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT smart_account_address
     FROM rotating_addresses
     WHERE LOWER(deposit_address) = ?
     ORDER BY created_at DESC
     LIMIT 1`,
    [normalized.toLowerCase()],
  );

  if (!rows.length) {
    return null;
  }

  return ethers.getAddress(rows[0].smart_account_address);
}
