import { Router, type Request, type Response } from 'express';
import { z } from 'zod';
import { ethers } from 'ethers';
import { ValidationError } from '../utils/errors.js';
import { config } from '../config/index.js';
import { submitSponsoredCall } from '../services/userOperation.js';
import { checkGasSponsorshipLimits } from '../services/limits.js';
import { withTransaction, pool } from '../db/index.js';
import { logger } from '../utils/logger.js';

const router = Router();

// Schema de validación para ejecutar acción
const executeActionSchema = z.object({
  userId: z.number().int().positive(),
  recipient: z.string().regex(/^0x[a-fA-F0-9]{40}$/, 'Invalid Ethereum address'),
  actionType: z.enum(['TIP', 'PAYMENT', 'UNLOCK', 'VOTE', 'DONATION', 'BOUNTY_CLAIM']),
  amount: z.string().regex(/^\d+(\.\d+)?$/, 'Invalid amount'),
  metadata: z.string().optional().default('{}'),
  smartAccountAddress: z.string().regex(/^0x[a-fA-F0-9]{40}$/).optional(),
});

// Mapeo de tipos de acción a números
const actionTypeMap: Record<string, number> = {
  TIP: 0,
  PAYMENT: 1,
  UNLOCK: 2,
  VOTE: 3,
  DONATION: 4,
  BOUNTY_CLAIM: 5,
};

/**
 * POST /actions/execute
 * Ejecuta una acción gasless (propina, pago, unlock, etc.)
 */
router.post('/execute', async (req: Request, res: Response) => {
  try {
    const params = executeActionSchema.safeParse(req.body);
    if (!params.success) {
      throw new ValidationError('Invalid request parameters', { issues: params.error.issues });
    }

    const { userId, recipient, actionType, amount, metadata, smartAccountAddress } = params.data;

    // Verificar límites diarios de gas sponsorship
    const limitsCheck = await checkGasSponsorshipLimits(userId);
    if (!limitsCheck.allowed) {
      return res.status(429).json({
        success: false,
        error: limitsCheck.message || 'Daily gas sponsorship limit exceeded',
        limits: {
          remainingUsd: limitsCheck.remainingUsd,
          remainingTxs: limitsCheck.remainingTxs,
        },
      });
    }

    // Obtener smart account del usuario si no se proporcionó
    let userSmartAccount = smartAccountAddress;
    if (!userSmartAccount) {
      const result = await withTransaction(async (conn) => {
        const [rows]: any = await conn.execute(
          'SELECT smart_account_address FROM smart_accounts WHERE user_id = ? AND status = "deployed" LIMIT 1',
          [userId]
        );
        return rows.length > 0 ? rows[0].smart_account_address : null;
      });
      
      if (!result) {
        throw new ValidationError('User does not have a deployed smart account');
      }
      userSmartAccount = result;
    }

    // Parsear amount a Wei
    const amountWei = ethers.parseEther(amount);

    // Cargar ABI del contrato GaslessActions
    const gaslessActionsAddress = process.env.GASLESS_ACTIONS_CONTRACT;
    if (!gaslessActionsAddress) {
      throw new Error('GASLESS_ACTIONS_CONTRACT not configured in environment');
    }

    // ABI simplificado (solo la función executeAction)
    const gaslessActionsABI = [
      'function executeAction(address recipient, uint8 actionType, uint256 amount, string metadata) external returns (bytes32)',
    ];

    const iface = new ethers.Interface(gaslessActionsABI);
    const callData = iface.encodeFunctionData('executeAction', [
      recipient,
      actionTypeMap[actionType],
      amountWei,
      metadata,
    ]);

    logger.info(
      {
        userId,
        recipient,
        actionType,
        amount,
        smartAccount: userSmartAccount,
      },
      'Executing gasless action'
    );

    // Enviar transacción patrocinada via Gelato
    const { taskId } = await submitSponsoredCall({
      target: gaslessActionsAddress,
      data: callData,
    });

    // Guardar registro en base de datos
    const actionRecord = await withTransaction(async (conn) => {
      const [result]: any = await conn.execute(
        `INSERT INTO gasless_actions 
        (user_id, smart_account_address, recipient, action_type, amount_wei, metadata, relay_task_id, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())`,
        [userId, userSmartAccount, recipient, actionType, amountWei.toString(), metadata, taskId]
      );

      return {
        actionId: result.insertId,
        taskId,
        status: 'pending',
      };
    });

    res.json({
      success: true,
      data: {
        actionId: actionRecord.actionId,
        taskId: actionRecord.taskId,
        status: 'pending',
        message: 'Action submitted successfully. It will be executed shortly.',
      },
    });
  } catch (error) {
    logger.error({ err: error }, 'Failed to execute gasless action');

    if (error instanceof ValidationError) {
      return res.status(400).json({
        success: false,
        error: error.message,
        details: error.details,
      });
    }

    res.status(500).json({
      success: false,
      error: 'Internal server error',
    });
  }
});

/**
 * GET /actions/status/:actionId
 * Consulta el estado de una acción ejecutada
 */
router.get('/status/:actionId', async (req: Request, res: Response) => {
  try {
    const actionId = parseInt(req.params.actionId, 10);
    if (isNaN(actionId)) {
      throw new ValidationError('Invalid action ID');
    }

    const action = await withTransaction(async (conn) => {
      const [rows]: any = await conn.execute(
        `SELECT 
          id, user_id, smart_account_address, recipient, action_type, 
          amount_wei, metadata, relay_task_id, status, tx_hash, 
          fail_reason, executed_at, created_at
        FROM gasless_actions 
        WHERE id = ?`,
        [actionId]
      );

      return rows.length > 0 ? rows[0] : null;
    });

    if (!action) {
      return res.status(404).json({
        success: false,
        error: 'Action not found',
      });
    }

    res.json({
      success: true,
      data: {
        actionId: action.id,
        userId: action.user_id,
        recipient: action.recipient,
        actionType: action.action_type,
        amount: ethers.formatEther(action.amount_wei),
        metadata: action.metadata,
        taskId: action.relay_task_id,
        status: action.status,
        txHash: action.tx_hash,
        failReason: action.fail_reason,
        executedAt: action.executed_at,
        createdAt: action.created_at,
      },
    });
  } catch (error) {
    logger.error({ err: error }, 'Failed to get action status');

    res.status(500).json({
      success: false,
      error: 'Internal server error',
    });
  }
});

/**
 * GET /actions/history
 * Obtiene el historial de acciones de un usuario
 */
router.get('/history', async (req: Request, res: Response) => {
  try {
    const userId = parseInt(req.query.userId as string, 10);
    const limit = Math.min(parseInt(req.query.limit as string, 10) || 20, 100);
    const offset = parseInt(req.query.offset as string, 10) || 0;

    if (isNaN(userId)) {
      throw new ValidationError('Invalid user ID');
    }

    const actions = await withTransaction(async (conn) => {
      const [rows]: any = await conn.execute(
        `SELECT 
          id, recipient, action_type, amount_wei, metadata, 
          status, tx_hash, executed_at, created_at
        FROM gasless_actions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?`,
        [userId, limit, offset]
      );

      return rows.map((row: any) => ({
        actionId: row.id,
        recipient: row.recipient,
        actionType: row.action_type,
        amount: ethers.formatEther(row.amount_wei),
        metadata: row.metadata,
        status: row.status,
        txHash: row.tx_hash,
        executedAt: row.executed_at,
        createdAt: row.created_at,
      }));
    });

    res.json({
      success: true,
      data: {
        actions,
        count: actions.length,
        limit,
        offset,
      },
    });
  } catch (error) {
    logger.error({ err: error }, 'Failed to get action history');

    res.status(500).json({
      success: false,
      error: 'Internal server error',
    });
  }
});

export default router;
