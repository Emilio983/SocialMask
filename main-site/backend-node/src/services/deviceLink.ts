import crypto from 'crypto';
import { RowDataPacket } from 'mysql2/promise';
import { pool, withTransaction } from '../db/index.js';
import { ensureSmartAccount } from './smartAccount.js';
import { ValidationError } from '../utils/errors.js';
import { logger } from '../utils/logger.js';

type LinkResult = {
  linkCode: string;
  qrToken: string;
  expiresAt: string;
};

function generateLinkCode(): string {
  return crypto.randomBytes(6).toString('hex').slice(0, 12).toUpperCase();
}

function base64UrlToBuffer(value?: string): Buffer | null {
  if (!value) {
    return null;
  }

  const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
  const pad = normalized.length % 4;
  const padded = pad ? normalized + '='.repeat(4 - pad) : normalized;
  try {
    return Buffer.from(padded, 'base64');
  } catch (error) {
    logger.warn({ err: error }, 'Failed to decode credentialId');
    return null;
  }
}

export async function createDeviceLink(userId: number): Promise<LinkResult> {
  if (!userId) {
    throw new ValidationError('userId required');
  }

  await pool.execute(
    'UPDATE device_links SET status = "expired" WHERE user_id = ? AND status = "pending" AND expires_at < NOW()',
    [userId],
  );

  const linkCode = generateLinkCode();
  const qrToken = crypto.randomBytes(32).toString('hex');
  const expiresAt = new Date(Date.now() + 10 * 60 * 1000);

  await pool.execute(
    'INSERT INTO device_links (user_id, link_code, qr_token, status, expires_at, created_at) VALUES (?, ?, ?, "pending", ?, NOW())',
    [userId, linkCode, qrToken, expiresAt.toISOString().slice(0, 19).replace('T', ' ')],
  );

  return {
    linkCode,
    qrToken,
    expiresAt: expiresAt.toISOString(),
  };
}

export async function listDeviceLinks(userId: number) {
  if (!userId) {
    throw new ValidationError('userId required');
  }

  const [rows] = await pool.execute<RowDataPacket[]>(
    'SELECT link_code, qr_token, status, expires_at, consumed_at FROM device_links WHERE user_id = ? AND status IN ("pending", "consumed") ORDER BY created_at DESC LIMIT 5',
    [userId],
  );

  return rows.map((row) => ({
    link_code: row.link_code,
    qr_token: row.qr_token,
    status: row.status,
    expires_at: row.expires_at,
    consumed_at: row.consumed_at,
  }));
}

export async function validateDeviceLink(linkCode?: string, qrToken?: string) {
  if (!linkCode && !qrToken) {
    throw new ValidationError('linkCode or qrToken required');
  }

  const condition = linkCode ? 'dl.link_code = ?' : 'dl.qr_token = ?';
  const value = linkCode ?? qrToken ?? '';

  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT dl.link_code, dl.qr_token, dl.status, dl.expires_at, u.alias
     FROM device_links dl
     JOIN users u ON u.user_id = dl.user_id
     WHERE dl.status = "pending" AND dl.expires_at > NOW() AND ${condition}
     LIMIT 1`,
    [value],
  );

  if (!rows.length) {
    throw new ValidationError('Código inválido o expirado');
  }

  const link = rows[0];

  return {
    link_code: link.link_code,
    qr_token: link.qr_token,
    status: link.status,
    expires_at: link.expires_at,
    alias: link.alias,
  };
}

type ConsumeLinkInput = {
  linkCode?: string;
  qrToken?: string;
  devicePublicKey: string;
  credentialId?: string;
  ownerAddress: string;
  deviceLabel?: string;
  platform?: string;
};

export async function consumeDeviceLink(input: ConsumeLinkInput) {
  if (!input.devicePublicKey) {
    throw new ValidationError('devicePublicKey required');
  }
  if (!input.ownerAddress) {
    throw new ValidationError('ownerAddress required');
  }

  if (!input.linkCode && !input.qrToken) {
    throw new ValidationError('linkCode or qrToken required');
  }

  const condition = input.linkCode ? 'dl.link_code = ?' : 'dl.qr_token = ?';
  const value = input.linkCode ?? input.qrToken ?? '';

  return withTransaction(async (conn) => {
    const [rows] = await conn.execute<RowDataPacket[]>(
      `SELECT dl.id, dl.link_code, dl.qr_token, dl.status, dl.expires_at, dl.user_id,
              u.username, u.alias, u.wallet_address, u.smart_account_address, u.primary_device_id
       FROM device_links dl
       JOIN users u ON u.user_id = dl.user_id
       WHERE dl.status = "pending" AND dl.expires_at > NOW() AND ${condition}
       FOR UPDATE`,
      [value],
    );

    if (!rows.length) {
      throw new ValidationError('Código inválido o expirado');
    }

    const link = rows[0];
    const userId = Number(link.user_id);
    const normalizedOwner = input.ownerAddress.toLowerCase();

    if (!link.wallet_address) {
      await conn.execute('UPDATE users SET wallet_address = ?, updated_at = NOW() WHERE user_id = ?', [normalizedOwner, userId]);
      link.wallet_address = normalizedOwner;
    }

    const smart = await ensureSmartAccount({ ownerAddress: normalizedOwner, devicePublicKey: input.devicePublicKey });

    await conn.execute(
      `INSERT INTO smart_accounts (user_id, smart_account_address, deployment_tx_hash, paymaster_policy_id, status, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, NOW(), NOW())
       ON DUPLICATE KEY UPDATE smart_account_address = VALUES(smart_account_address), deployment_tx_hash = VALUES(deployment_tx_hash), paymaster_policy_id = VALUES(paymaster_policy_id), status = VALUES(status), updated_at = NOW()`,
      [
        userId,
        smart.smartAccountAddress,
        smart.deploymentTxHash,
        smart.paymasterPolicyId,
        smart.status,
      ],
    );

    await conn.execute(
      'UPDATE users SET smart_account_address = ?, updated_at = NOW() WHERE user_id = ?',
      [smart.smartAccountAddress, userId],
    );

    const credentialBuffer = base64UrlToBuffer(input.credentialId ?? undefined);
    const hasPrimary = link.primary_device_id !== null && link.primary_device_id !== undefined;
    const isPrimary = hasPrimary ? 0 : 1;

    await conn.execute(
      `INSERT INTO user_devices (user_id, device_label, device_public_key, credential_id, platform, is_primary, added_via, last_used_at, revoked_at, created_at)
       VALUES (?, ?, ?, ?, ?, ?, 'link_code', NOW(), NULL, NOW())
       ON DUPLICATE KEY UPDATE device_public_key = VALUES(device_public_key), platform = VALUES(platform), revoked_at = NULL, last_used_at = NOW()`,
      [
        userId,
        input.deviceLabel ?? null,
        input.devicePublicKey,
        credentialBuffer,
        input.platform ?? null,
        isPrimary,
      ],
    );

    const [deviceRows] = await conn.execute<RowDataPacket[]>(
      'SELECT id, device_label, platform, is_primary, added_via, last_used_at, revoked_at, created_at FROM user_devices WHERE user_id = ? AND device_public_key = ? ORDER BY created_at DESC LIMIT 1',
      [userId, input.devicePublicKey],
    );

    if (!deviceRows.length) {
      throw new ValidationError('No se pudo registrar el dispositivo');
    }

    const deviceRow = deviceRows[0];
    const deviceId = Number(deviceRow.id);

    if (!hasPrimary) {
      await conn.execute('UPDATE users SET primary_device_id = ? WHERE user_id = ?', [deviceId, userId]);
      deviceRow.is_primary = 1;
    }

    await conn.execute('UPDATE device_links SET status = "consumed", consumed_at = NOW() WHERE id = ?', [link.id]);

    return {
      user: {
        user_id: userId,
        username: link.username,
        alias: link.alias,
        wallet_address: normalizedOwner,
        smart_account_address: smart.smartAccountAddress,
        paymaster_policy_id: smart.paymasterPolicyId,
      },
      device: {
        id: deviceId,
        device_label: deviceRow.device_label,
        platform: deviceRow.platform,
        is_primary: Number(deviceRow.is_primary) === 1,
        last_used_at: deviceRow.last_used_at,
      },
      link: {
        link_code: link.link_code,
        qr_token: link.qr_token,
        status: 'consumed',
        expires_at: link.expires_at,
      },
    };
  });
}

export async function listDevices(userId: number) {
  if (!userId) {
    throw new ValidationError('userId required');
  }

  const [rows] = await pool.execute<RowDataPacket[]>(
    'SELECT id, device_label, platform, is_primary, added_via, last_used_at, revoked_at, created_at FROM user_devices WHERE user_id = ? ORDER BY is_primary DESC, created_at DESC',
    [userId],
  );

  return rows.map((row) => ({
    id: row.id,
    device_label: row.device_label,
    platform: row.platform,
    is_primary: Number(row.is_primary) === 1,
    added_via: row.added_via,
    last_used_at: row.last_used_at,
    revoked_at: row.revoked_at,
    created_at: row.created_at,
  }));
}

export async function revokeDevice(userId: number, deviceId: number) {
  if (!userId || !deviceId) {
    throw new ValidationError('userId and deviceId required');
  }

  return withTransaction(async (conn) => {
    const [rows] = await conn.execute<RowDataPacket[]>(
      'SELECT id, is_primary FROM user_devices WHERE id = ? AND user_id = ? FOR UPDATE',
      [deviceId, userId],
    );

    if (!rows.length) {
      throw new ValidationError('Dispositivo no encontrado');
    }

    if (rows[0].is_primary) {
      throw new ValidationError('No puedes revocar el dispositivo principal');
    }

    await conn.execute('UPDATE user_devices SET revoked_at = NOW() WHERE id = ?', [deviceId]);

    return {
      device_id: deviceId,
    };
  });
}
