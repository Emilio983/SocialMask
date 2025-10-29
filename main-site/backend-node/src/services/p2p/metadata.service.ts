/**
 * ============================================
 * P2P METADATA SERVICE
 * ============================================
 * Sistema de metadatos P2P usando MySQL como backend
 * Reemplaza Gun.js y OrbitDB (deprecados)
 * 
 * Esquema de metadatos:
 * {
 *   cid: string,          // IPFS Content ID
 *   iv: string,           // Initialization Vector (base64)
 *   senderPub: string,    // Sender's public key (base64)
 *   recipients: array,    // Array of recipient IDs
 *   wrappedKeys: object,  // Wrapped AES keys per recipient
 *   ts: number,           // Timestamp
 *   meta: object          // Additional metadata
 * }
 */

import { config } from '../../config/index.js';
import mysql from 'mysql2/promise';

export class P2PMetadataService {
  private pool: mysql.Pool;

  constructor() {
    this.pool = mysql.createPool({
      host: config.database.host,
      port: config.database.port,
      user: config.database.user,
      password: config.database.password,
      database: config.database.name,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0
    });
  }

  /**
   * Store P2P metadata
   */
  async storeMetadata(data: {
    messageId?: number;
    cid: string;
    iv: string;
    senderPub: string;
    senderId: number;
    recipients: number[];
    wrappedKeys: Record<string, string>;
    ts: number;
    meta?: any;
  }) {
    const connection = await this.pool.getConnection();
    
    try {
      const [result] = await connection.execute(
        `INSERT INTO p2p_metadata 
        (message_id, cid, iv, sender_pub, sender_id, recipient_ids, wrapped_keys, timestamp, metadata, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
        [
          data.messageId || null,
          data.cid,
          data.iv,
          data.senderPub,
          data.senderId,
          JSON.stringify(data.recipients),
          JSON.stringify(data.wrappedKeys),
          data.ts,
          JSON.stringify(data.meta || {})
        ]
      );

      return {
        success: true,
        id: (result as any).insertId,
        cid: data.cid
      };
    } catch (error) {
      console.error('Error storing P2P metadata:', error);
      throw error;
    } finally {
      connection.release();
    }
  }

  /**
   * Get metadata by CID
   */
  async getMetadataByCid(cid: string) {
    const connection = await this.pool.getConnection();
    
    try {
      const [rows] = await connection.execute(
        `SELECT * FROM p2p_metadata WHERE cid = ? ORDER BY created_at DESC LIMIT 1`,
        [cid]
      );

      if ((rows as any[]).length === 0) {
        return null;
      }

      const row = (rows as any[])[0];
      return this.parseMetadataRow(row);
    } finally {
      connection.release();
    }
  }

  /**
   * Get metadata for user (as recipient)
   */
  async getMetadataForRecipient(recipientId: number, limit: number = 50, offset: number = 0) {
    const connection = await this.pool.getConnection();
    
    try {
      const [rows] = await connection.execute(
        `SELECT * FROM p2p_metadata 
         WHERE JSON_CONTAINS(recipient_ids, ?, '$')
         ORDER BY timestamp DESC
         LIMIT ? OFFSET ?`,
        [recipientId.toString(), limit, offset]
      );

      return (rows as any[]).map(row => this.parseMetadataRow(row));
    } finally {
      connection.release();
    }
  }

  /**
   * Get metadata by sender
   */
  async getMetadataBySender(senderId: number, limit: number = 50, offset: number = 0) {
    const connection = await this.pool.getConnection();
    
    try {
      const [rows] = await connection.execute(
        `SELECT * FROM p2p_metadata 
         WHERE sender_id = ?
         ORDER BY timestamp DESC
         LIMIT ? OFFSET ?`,
        [senderId, limit, offset]
      );

      return (rows as any[]).map(row => this.parseMetadataRow(row));
    } finally {
      connection.release();
    }
  }

  /**
   * Get metadata by message ID
   */
  async getMetadataByMessageId(messageId: number) {
    const connection = await this.pool.getConnection();
    
    try {
      const [rows] = await connection.execute(
        `SELECT * FROM p2p_metadata WHERE message_id = ? ORDER BY created_at DESC`,
        [messageId]
      );

      return (rows as any[]).map(row => this.parseMetadataRow(row));
    } finally {
      connection.release();
    }
  }

  /**
   * Delete metadata by CID
   */
  async deleteMetadata(cid: string, userId: number) {
    const connection = await this.pool.getConnection();
    
    try {
      // Only allow sender to delete
      const [result] = await connection.execute(
        `DELETE FROM p2p_metadata WHERE cid = ? AND sender_id = ?`,
        [cid, userId]
      );

      return {
        success: (result as any).affectedRows > 0,
        deleted: (result as any).affectedRows
      };
    } finally {
      connection.release();
    }
  }

  /**
   * Get conversation metadata (between two users)
   */
  async getConversationMetadata(user1Id: number, user2Id: number, limit: number = 50) {
    const connection = await this.pool.getConnection();
    
    try {
      const [rows] = await connection.execute(
        `SELECT * FROM p2p_metadata 
         WHERE (sender_id = ? AND JSON_CONTAINS(recipient_ids, ?, '$'))
            OR (sender_id = ? AND JSON_CONTAINS(recipient_ids, ?, '$'))
         ORDER BY timestamp DESC
         LIMIT ?`,
        [user1Id, user2Id.toString(), user2Id, user1Id.toString(), limit]
      );

      return (rows as any[]).map(row => this.parseMetadataRow(row));
    } finally {
      connection.release();
    }
  }

  /**
   * Parse database row to metadata object
   */
  private parseMetadataRow(row: any) {
    return {
      id: row.id,
      messageId: row.message_id,
      cid: row.cid,
      iv: row.iv,
      senderPub: row.sender_pub,
      senderId: row.sender_id,
      recipients: JSON.parse(row.recipient_ids),
      wrappedKeys: JSON.parse(row.wrapped_keys),
      ts: row.timestamp,
      meta: JSON.parse(row.metadata),
      createdAt: row.created_at
    };
  }

  /**
   * Migrate data from Gun.js (if any legacy data exists)
   */
  async migrateFromGunjs(gunData: any[]) {
    let migrated = 0;
    let errors = 0;

    for (const item of gunData) {
      try {
        await this.storeMetadata({
          cid: item.cid || item.ipfsHash,
          iv: item.iv,
          senderPub: item.senderPub || item.senderPublicKey,
          senderId: item.senderId || item.sender_id,
          recipients: item.recipients || [],
          wrappedKeys: item.wrappedKeys || {},
          ts: item.ts || item.timestamp || Date.now(),
          meta: item.meta || item.metadata || {}
        });
        migrated++;
      } catch (error) {
        console.error('Migration error for item:', item, error);
        errors++;
      }
    }

    return {
      success: true,
      migrated,
      errors,
      total: gunData.length
    };
  }

  /**
   * Get statistics
   */
  async getStats() {
    const connection = await this.pool.getConnection();
    
    try {
      const [countResult] = await connection.execute(
        `SELECT COUNT(*) as total FROM p2p_metadata`
      );
      
      const [recentResult] = await connection.execute(
        `SELECT COUNT(*) as recent FROM p2p_metadata 
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)`
      );

      return {
        total: (countResult as any[])[0].total,
        last24h: (recentResult as any[])[0].recent
      };
    } finally {
      connection.release();
    }
  }

  /**
   * Close connection pool
   */
  async close() {
    await this.pool.end();
  }
}
