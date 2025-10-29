/**
 * ============================================
 * P2P METADATA ROUTES
 * ============================================
 * Endpoints para gestionar metadatos P2P
 */

import { Router } from 'express';
import { P2PMetadataService } from '../services/p2p/metadata.service.js';

const router = Router();
const metadataService = new P2PMetadataService();

/**
 * POST /p2p/metadata/store
 * Store new P2P metadata
 */
router.post('/metadata/store', async (req, res) => {
  try {
    const { messageId, cid, iv, senderPub, senderId, recipients, wrappedKeys, ts, meta } = req.body;

    // Validate required fields
    if (!cid || !iv || !senderPub || !senderId || !recipients || !wrappedKeys || !ts) {
      return res.status(400).json({
        success: false,
        message: 'Missing required fields: cid, iv, senderPub, senderId, recipients, wrappedKeys, ts'
      });
    }

    const result = await metadataService.storeMetadata({
      messageId,
      cid,
      iv,
      senderPub,
      senderId,
      recipients,
      wrappedKeys,
      ts,
      meta
    });

    res.json(result);
  } catch (error: any) {
    console.error('Error storing metadata:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to store metadata',
      error: error.message
    });
  }
});

/**
 * GET /p2p/metadata/cid/:cid
 * Get metadata by CID
 */
router.get('/metadata/cid/:cid', async (req, res) => {
  try {
    const { cid } = req.params;
    const metadata = await metadataService.getMetadataByCid(cid);

    if (!metadata) {
      return res.status(404).json({
        success: false,
        message: 'Metadata not found'
      });
    }

    res.json({
      success: true,
      metadata
    });
  } catch (error: any) {
    console.error('Error fetching metadata:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch metadata',
      error: error.message
    });
  }
});

/**
 * GET /p2p/metadata/recipient/:userId
 * Get metadata for recipient
 */
router.get('/metadata/recipient/:userId', async (req, res) => {
  try {
    const userId = parseInt(req.params.userId);
    const limit = parseInt(req.query.limit as string) || 50;
    const offset = parseInt(req.query.offset as string) || 0;

    const metadata = await metadataService.getMetadataForRecipient(userId, limit, offset);

    res.json({
      success: true,
      count: metadata.length,
      metadata
    });
  } catch (error: any) {
    console.error('Error fetching metadata:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch metadata',
      error: error.message
    });
  }
});

/**
 * GET /p2p/metadata/sender/:userId
 * Get metadata by sender
 */
router.get('/metadata/sender/:userId', async (req, res) => {
  try {
    const userId = parseInt(req.params.userId);
    const limit = parseInt(req.query.limit as string) || 50;
    const offset = parseInt(req.query.offset as string) || 0;

    const metadata = await metadataService.getMetadataBySender(userId, limit, offset);

    res.json({
      success: true,
      count: metadata.length,
      metadata
    });
  } catch (error: any) {
    console.error('Error fetching metadata:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch metadata',
      error: error.message
    });
  }
});

/**
 * GET /p2p/metadata/conversation/:user1/:user2
 * Get conversation metadata between two users
 */
router.get('/metadata/conversation/:user1/:user2', async (req, res) => {
  try {
    const user1 = parseInt(req.params.user1);
    const user2 = parseInt(req.params.user2);
    const limit = parseInt(req.query.limit as string) || 50;

    const metadata = await metadataService.getConversationMetadata(user1, user2, limit);

    res.json({
      success: true,
      count: metadata.length,
      metadata
    });
  } catch (error: any) {
    console.error('Error fetching conversation:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch conversation',
      error: error.message
    });
  }
});

/**
 * DELETE /p2p/metadata/:cid
 * Delete metadata by CID
 */
router.delete('/metadata/:cid', async (req, res) => {
  try {
    const { cid } = req.params;
    const userId = parseInt(req.body.userId);

    if (!userId) {
      return res.status(400).json({
        success: false,
        message: 'userId required'
      });
    }

    const result = await metadataService.deleteMetadata(cid, userId);

    res.json(result);
  } catch (error: any) {
    console.error('Error deleting metadata:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to delete metadata',
      error: error.message
    });
  }
});

/**
 * GET /p2p/metadata/stats
 * Get P2P statistics
 */
router.get('/metadata/stats', async (req, res) => {
  try {
    const stats = await metadataService.getStats();

    res.json({
      success: true,
      stats
    });
  } catch (error: any) {
    console.error('Error fetching stats:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch stats',
      error: error.message
    });
  }
});

/**
 * POST /p2p/metadata/migrate
 * Migrate from Gun.js (legacy)
 */
router.post('/metadata/migrate', async (req, res) => {
  try {
    const { gunData } = req.body;

    if (!Array.isArray(gunData)) {
      return res.status(400).json({
        success: false,
        message: 'gunData must be an array'
      });
    }

    const result = await metadataService.migrateFromGunjs(gunData);

    res.json(result);
  } catch (error: any) {
    console.error('Error migrating data:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to migrate data',
      error: error.message
    });
  }
});

export default router;
