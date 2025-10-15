/**
 * Threshold Wallet Service
 * Implementa transferencias seguras usando Shamir's Secret Sharing
 * Requiere 3 de 5 fragmentos para reconstruir la clave privada
 */

import { ethers } from 'ethers';
import { pool } from '../db/index.js';
import { RowDataPacket, ResultSetHeader } from 'mysql2/promise';
import { logger } from '../utils/logger.js';
import { config } from '../config/index.js';
import { getHttpProvider } from '../utils/web3.js';
import crypto from 'crypto';

// Importar la librería de Shamir's Secret Sharing
// @ts-ignore
import * as secrets from 'secrets.js-grempe';

const THRESHOLD = 3; // Mínimo de fragmentos requeridos
const TOTAL_SHARES = 5; // Total de fragmentos generados

interface KeyShare {
  shareIndex: number;
  encryptedShare: string;
  iv: string;
  authTag: string;
  shareHash: string;
  location: string;
}

interface ShareMetadata {
  shareSetId: string;
  userId: number;
  shares: KeyShare[];
}

/**
 * Genera fragmentos de una clave privada usando Shamir's Secret Sharing
 */
export function splitPrivateKey(privateKey: string): string[] {
  // Limpiar la clave
  const cleanKey = privateKey.replace(/^0x/, '');
  
  if (cleanKey.length !== 64) {
    throw new Error('Invalid private key length');
  }

  // Generar fragmentos
  const shares = secrets.share(cleanKey, TOTAL_SHARES, THRESHOLD);
  return shares;
}

/**
 * Reconstruye una clave privada desde los fragmentos
 */
export function combineShares(shares: string[]): string {
  if (shares.length < THRESHOLD) {
    throw new Error(`Need at least ${THRESHOLD} shares to reconstruct`);
  }

  const reconstructed = secrets.combine(shares.slice(0, THRESHOLD));
  return '0x' + reconstructed;
}

/**
 * Encripta un fragmento usando AES-256-GCM
 */
function encryptShare(share: string, password: string): {
  encrypted: string;
  iv: string;
  authTag: string;
} {
  const key = crypto.scryptSync(password, 'salt', 32);
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
  
  let encrypted = cipher.update(share, 'utf8', 'hex');
  encrypted += cipher.final('hex');
  
  const authTag = cipher.getAuthTag();
  
  return {
    encrypted,
    iv: iv.toString('hex'),
    authTag: authTag.toString('hex')
  };
}

/**
 * Desencripta un fragmento
 */
function decryptShare(encryptedData: {
  encrypted: string;
  iv: string;
  authTag: string;
}, password: string): string {
  const key = crypto.scryptSync(password, 'salt', 32);
  const decipher = crypto.createDecipheriv(
    'aes-256-gcm',
    key,
    Buffer.from(encryptedData.iv, 'hex')
  );
  
  decipher.setAuthTag(Buffer.from(encryptedData.authTag, 'hex'));
  
  let decrypted = decipher.update(encryptedData.encrypted, 'hex', 'utf8');
  decrypted += decipher.final('utf8');
  
  return decrypted;
}

/**
 * Almacena fragmentos encriptados en la base de datos
 */
export async function storeKeyShares(
  userId: number,
  privateKey: string,
  password: string
): Promise<string> {
  const conn = await pool.getConnection();
  
  try {
    await conn.beginTransaction();
    
    // Generar fragmentos
    const shares = splitPrivateKey(privateKey);
    
    // Generar ID único para el set
    const shareSetId = `shares_${userId}_${Date.now()}_${crypto.randomBytes(8).toString('hex')}`;
    
    // Encriptar y almacenar cada fragmento
    for (let i = 0; i < shares.length; i++) {
      const share = shares[i];
      const shareIndex = i + 1;
      
      // Encriptar el fragmento
      const encrypted = encryptShare(share, password);
      
      // Hash para verificación
      const shareHash = crypto.createHash('sha256').update(share).digest('hex');
      
      // Determinar ubicación
      const locations = [
        'client_indexeddb',
        'server_database',
        'secondary_device',
        'offline_backup',
        'hsm_cold_storage'
      ];
      
      // Insertar en la base de datos
      await conn.execute(
        `INSERT INTO key_shares 
        (user_id, share_set_id, share_index, encrypted_share, iv, auth_tag, share_hash, location)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          userId,
          shareSetId,
          shareIndex,
          encrypted.encrypted,
          encrypted.iv,
          encrypted.authTag,
          shareHash,
          locations[i]
        ]
      );
    }
    
    await conn.commit();
    logger.info(`Stored ${TOTAL_SHARES} key shares for user ${userId}`);
    
    return shareSetId;
  } catch (error: unknown) {
    await conn.rollback();
    logger.error({ err: error, userId }, 'Error storing key shares');
    throw error;
  } finally {
    conn.release();
  }
}

/**
 * Recupera y reconstruye la clave privada
 * IMPORTANTE: Solo se usa cuando es absolutamente necesario para firmar transacciones
 */
export async function reconstructPrivateKey(
  userId: number,
  password: string,
  shareSetId?: string
): Promise<string> {
  const conn = await pool.getConnection();
  
  try {
    // Obtener fragmentos de la base de datos
    let query = `
      SELECT share_index, encrypted_share, iv, auth_tag, share_hash, location
      FROM key_shares
      WHERE user_id = ?
    `;
    
    const params: any[] = [userId];
    
    if (shareSetId) {
      query += ' AND share_set_id = ?';
      params.push(shareSetId);
    }
    
    query += ' ORDER BY created_at DESC LIMIT ?';
    params.push(THRESHOLD);
    
    const [rows] = await conn.execute<RowDataPacket[]>(query, params);
    
    if (rows.length < THRESHOLD) {
      throw new Error(`Insufficient key shares. Need ${THRESHOLD}, found ${rows.length}`);
    }
    
    // Desencriptar fragmentos
    const decryptedShares: string[] = [];
    
    for (const row of rows) {
      try {
        const decrypted = decryptShare({
          encrypted: row.encrypted_share,
          iv: row.iv,
          authTag: row.auth_tag
        }, password);
        
        // Verificar hash
        const computedHash = crypto.createHash('sha256').update(decrypted).digest('hex');
        if (computedHash !== row.share_hash) {
          throw new Error(`Share hash mismatch for share ${row.share_index}`);
        }
        
        decryptedShares.push(decrypted);
      } catch (error: unknown) {
        logger.error({ err: error, shareIndex: row.share_index }, 'Error decrypting share');
        throw new Error('Failed to decrypt key share. Invalid password or corrupted data.');
      }
    }
    
    // Reconstruir la clave privada
    const privateKey = combineShares(decryptedShares);
    
    // Log de auditoría
    await conn.execute(
      `INSERT INTO security_audit_log 
      (user_id, action, details, ip_address, timestamp)
      VALUES (?, ?, ?, ?, NOW())`,
      [
        userId,
        'private_key_reconstructed',
        JSON.stringify({ shareSetId, sharesUsed: decryptedShares.length }),
        'internal'
      ]
    );
    
    return privateKey;
  } finally {
    conn.release();
  }
}

/**
 * Ejecuta una transferencia segura usando threshold cryptography
 */
export async function executeSecureTransfer(params: {
  userId: number;
  smartAccountAddress: string;
  token: 'SPHE' | 'USDT';
  toAddress: string;
  amount: string;
  password: string;
}): Promise<{
  txHash: string;
  status: string;
}> {
  const { userId, smartAccountAddress, token, toAddress, amount, password } = params;
  
  logger.info(`Executing secure transfer for user ${userId}`);
  
  // Obtener configuración del token
  const tokenConfig = token === 'SPHE' ? config.tokens.sphe : config.tokens.usdt;
  
  if (!tokenConfig || !tokenConfig.address) {
    throw new Error('Token configuration not found');
  }
  
  // Convertir amount a unidades raw
  const amountBigInt = ethers.parseUnits(amount, tokenConfig.decimals);
  
  // Reconstruir la clave privada usando threshold cryptography
  let privateKey: string;
  try {
    privateKey = await reconstructPrivateKey(userId, password);
  } catch (error: unknown) {
    logger.error({ err: error, userId }, 'Failed to reconstruct private key');
    throw new Error('Authentication failed. Invalid password or insufficient key shares.');
  }
  
  // Crear wallet y provider
  const provider = getHttpProvider();
  const wallet = new ethers.Wallet(privateKey, provider);
  
  // Verificar que el wallet corresponde a la smart account
  // (En producción, verificar ownership correctamente)
  
  // Crear contrato ERC20
  const erc20Abi = [
    'function transfer(address to, uint256 amount) returns (bool)',
    'function balanceOf(address account) view returns (uint256)'
  ];
  
  const tokenContract = new ethers.Contract(
    tokenConfig.address,
    erc20Abi,
    wallet
  );
  
  // Verificar balance
  const balance = await tokenContract.balanceOf(smartAccountAddress);
  
  if (balance < amountBigInt) {
    throw new Error(
      `Insufficient balance. Available: ${ethers.formatUnits(balance, tokenConfig.decimals)} ${token}`
    );
  }
  
  // Ejecutar transferencia
  logger.info(`Sending ${amount} ${token} from ${smartAccountAddress} to ${toAddress}`);
  
  const tx = await tokenContract.transfer(toAddress, amountBigInt);
  const receipt = await tx.wait();
  
  logger.info(`Transfer completed. TX Hash: ${receipt.hash}`);
  
  // Registrar en historial
  await pool.execute(
    `INSERT INTO transaction_history 
    (user_id, type, token, amount, to_address, from_address, tx_hash, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
    [
      userId,
      'send',
      token,
      amount,
      toAddress,
      smartAccountAddress,
      receipt.hash,
      'completed'
    ]
  );
  
  // Log de auditoría
  await pool.execute(
    `INSERT INTO security_audit_log 
    (user_id, action, details, ip_address, timestamp)
    VALUES (?, ?, ?, ?, NOW())`,
    [
      userId,
      'secure_transfer_executed',
      JSON.stringify({
        token,
        amount,
        to: toAddress,
        from: smartAccountAddress,
        txHash: receipt.hash
      }),
      'internal'
    ]
  );
  
  return {
    txHash: receipt.hash,
    status: 'completed'
  };
}

/**
 * Verifica si un usuario tiene fragmentos de clave almacenados
 */
export async function hasKeyShares(userId: number): Promise<boolean> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    'SELECT COUNT(*) as count FROM key_shares WHERE user_id = ?',
    [userId]
  );
  
  return rows[0].count >= THRESHOLD;
}

/**
 * Elimina fragmentos antiguos de manera segura
 */
export async function rotateKeyShares(
  userId: number,
  newPrivateKey: string,
  password: string
): Promise<string> {
  const conn = await pool.getConnection();
  
  try {
    await conn.beginTransaction();
    
    // Generar nuevos fragmentos
    const newShareSetId = await storeKeyShares(userId, newPrivateKey, password);
    
    // Eliminar fragmentos antiguos (mantener solo los últimos 2 sets por seguridad)
    await conn.execute(
      `DELETE FROM key_shares 
       WHERE user_id = ? 
       AND share_set_id NOT IN (
         SELECT share_set_id FROM (
           SELECT DISTINCT share_set_id 
           FROM key_shares 
           WHERE user_id = ? 
           ORDER BY created_at DESC 
           LIMIT 2
         ) AS recent
       )`,
      [userId, userId]
    );
    
    await conn.commit();
    
    logger.info(`Rotated key shares for user ${userId}`);
    
    return newShareSetId;
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}
