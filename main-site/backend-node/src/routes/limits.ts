import { Router } from 'express';
import { logger } from '../utils/logger.js';
import { AppError } from '../utils/errors.js';
import { checkGasSponsorshipLimits, getUserDailyUsage } from '../services/limits.js';
import { config } from '../config/index.js';

export const limitsRouter = Router();

// GET /limits/check - Verificar límites de gas sponsorship
limitsRouter.get('/check', async (req, res, next) => {
  try {
    const { userId } = req.query;

    if (!userId) {
      throw new AppError(400, 'missing_params', 'userId is required');
    }

    const limits = await checkGasSponsorshipLimits(parseInt(userId as string));

    res.json({
      success: true,
      limits: {
        allowed: limits.allowed,
        remainingUsd: limits.remainingUsd,
        remainingTxs: limits.remainingTxs,
        maxDailyUsd: config.gelato.gasSponsorLimitUsd,
        maxDailyTxs: config.gelato.txsPerDayLimit,
        message: limits.message,
      },
    });
  } catch (error) {
    next(error);
  }
});

// GET /limits/usage - Obtener uso diario actual
limitsRouter.get('/usage', async (req, res, next) => {
  try {
    const { userId } = req.query;

    if (!userId) {
      throw new AppError(400, 'missing_params', 'userId is required');
    }

    const usage = await getUserDailyUsage(parseInt(userId as string));

    res.json({
      success: true,
      usage: {
        txCount: usage.txCount,
        totalUsd: usage.totalUsd,
        maxDailyUsd: config.gelato.gasSponsorLimitUsd,
        maxDailyTxs: config.gelato.txsPerDayLimit,
        percentageUsd: (usage.totalUsd / config.gelato.gasSponsorLimitUsd) * 100,
        percentageTxs: (usage.txCount / config.gelato.txsPerDayLimit) * 100,
      },
    });
  } catch (error) {
    next(error);
  }
});
