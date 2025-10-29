import { Router } from 'express';
import { logger } from '../utils/logger.js';
import { AppError } from '../utils/errors.js';
import { checkGasSponsorshipLimits, registerSponsoredTx } from '../services/limits.js';
import { getZeroXQuote } from '../services/swap.js';
import { 
  recordWithdrawEvent,
  getDailyWithdrawLimits,
  getWithdrawHistory,
  validateWithdrawAddress
} from '../services/withdrawEvents.js';
import { ethers } from 'ethers';
import { config } from '../config/index.js';
import { submitSponsoredCall } from '../services/userOperation.js';

export const withdrawRouter = Router();

// POST /withdraw - Iniciar retiro SPHE->USDT->dirección externa
withdrawRouter.post('/', async (req, res, next) => {
  try {
    const { userId, smartAccountAddress, destinationAddress, amountSphe, slippageBps = 50 } = req.body;

    // Validaciones
    if (!userId || !smartAccountAddress || !destinationAddress || !amountSphe) {
      throw new AppError(400, 'missing_params', 'Missing required parameters');
    }

    const amountSpheNum = parseFloat(amountSphe);
    if (isNaN(amountSpheNum) || amountSpheNum <= 0) {
      throw new AppError(400, 'invalid_amount', 'Invalid SPHE amount');
    }

    // Validar dirección de destino
    const isValidAddress = await validateWithdrawAddress(destinationAddress);
    if (!isValidAddress) {
      throw new AppError(400, 'invalid_address', 'Invalid destination address');
    }

    // Verificar límites de gas sponsorship
    const limits = await checkGasSponsorshipLimits(userId);
    if (!limits.allowed) {
      throw new AppError(429, 'limits_exceeded', limits.message || 'Daily limits exceeded');
    }

    logger.info({ userId, amountSphe, destinationAddress }, 'Processing withdraw request');

    // Convertir amounts a Wei
    const amountSpheWei = ethers.parseUnits(amountSphe.toString(), config.tokens.sphe.decimals);

    // 1. Obtener quote para SPHE->USDT
    const quote = await getZeroXQuote({
      sellAmount: amountSpheWei.toString(),
      taker: smartAccountAddress,
    });

    const estimatedUsdtWei = BigInt(quote.buyAmount);

    logger.info({ 
      amountSphe: ethers.formatUnits(amountSpheWei, 18),
      estimatedUsdt: ethers.formatUnits(estimatedUsdtWei, 6)
    }, 'Swap quote obtained');

    // 2. Ejecutar swap SPHE->USDT via Gelato
    const swapResult = await submitSponsoredCall({
      target: quote.to,
      data: quote.data,
      value: quote.value,
    });

    logger.info({ taskId: swapResult.taskId }, 'Swap SPHE→USDT submitted to Gelato');

    // 3. Preparar transferencia USDT→externa (se ejecutará después del swap por el worker)
    // Por ahora, el transfer_task_id será el mismo que swap_task_id
    // El worker withdrawMonitor detectará cuando el swap complete y ejecutará el transfer
    
    // 4. Registrar evento de retiro con NUEVO esquema
    const withdrawId = await recordWithdrawEvent({
      user_id: userId,
      smart_account_address: smartAccountAddress,
      external_address: destinationAddress,
      amount_sphe_wei: amountSpheWei,
      amount_usdt_wei: estimatedUsdtWei,
      swap_task_id: swapResult.taskId,
      transfer_task_id: swapResult.taskId, // Placeholder, se actualizará cuando swap complete
      status: 'pending'
    });

    // 5. Registrar uso de gas sponsorship (estimado)
    const estimatedGasUsd = 0.10; // Estimación: swap + transfer
    await registerSponsoredTx(userId, estimatedGasUsd);

    res.json({
      success: true,
      data: {
        withdrawId,
        swapTaskId: swapResult.taskId,
        amountSphe: ethers.formatUnits(amountSpheWei, 18),
        estimatedUsdt: ethers.formatUnits(estimatedUsdtWei, 6),
        destinationAddress,
        estimatedTime: '2-5 minutes'
      },
      message: 'Withdraw initiated. Swap will be processed, then USDT will be sent to destination.',
    });

  } catch (error) {
    next(error);
  }
});
// GET /withdraw/history - Obtener historial de retiros
withdrawRouter.get('/history', async (req, res, next) => {
  try {
    const { userId, limit = 10 } = req.query;

    if (!userId) {
      throw new AppError(400, 'missing_params', 'userId is required');
    }

    const history = await getWithdrawHistory(
      parseInt(userId as string),
      parseInt(limit as string)
    );

    res.json({
      success: true,
      data: {
        withdraws: history
      },
    });

  } catch (error) {
    next(error);
  }
});

// GET /withdraw/limits - Obtener límites diarios de retiro
withdrawRouter.get('/limits', async (req, res, next) => {
  try {
    const { userId } = req.query;

    if (!userId) {
      throw new AppError(400, 'missing_params', 'userId is required');
    }

    const limits = await getDailyWithdrawLimits(parseInt(userId as string));

    res.json({
      success: true,
      data: limits,
    });

  } catch (error) {
    next(error);
  }
});
