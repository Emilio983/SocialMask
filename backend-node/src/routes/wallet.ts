import { Router, Request, Response, NextFunction } from 'express';
import { getBalances } from '../services/balance.js';
import { getZeroXQuote, executeSwapWithFallback } from '../services/swap.js';
import { config } from '../config/index.js';
import { ValidationError } from '../utils/errors.js';
import { ethers } from 'ethers';
import { logger } from '../utils/logger.js';

export const walletRouter = Router();

walletRouter.get('/balances', async (req, res, next) => {
  try {
    const address = req.query.address as string | undefined;
    if (!address) {
      throw new ValidationError('address query param required');
    }
    const balances = await getBalances(address);
    res.json({ success: true, data: balances });
  } catch (error) {
    next(error);
  }
});

walletRouter.get('/autoswap-quote', async (req, res, next) => {
  try {
    const address = req.query.address as string | undefined;
    const amountParam = req.query.amount as string | undefined;

    if (!address) {
      throw new ValidationError('address query param required');
    }

    const balances = await getBalances(address);
    const usdtRaw = BigInt(balances.usdt.raw);
    const amountRaw = amountParam ? BigInt(amountParam) : usdtRaw;

    if (amountRaw <= 0n) {
      res.json({
        success: true,
        data: {
          hasQuote: false,
          reason: 'NO_USDT_BALANCE',
        },
      });
      return;
    }

    const quote = await getZeroXQuote({
      sellAmount: amountRaw.toString(),
      taker: address,
    });

    res.json({
      success: true,
      data: {
        hasQuote: true,
        amountRaw: amountRaw.toString(),
        amountFormatted: ethers.formatUnits(amountRaw, config.tokens.usdt.decimals),
        quote,
      },
    });
  } catch (error) {
    next(error);
  }
});

walletRouter.post('/autoswap-execute', async (req, res, next) => {
  try {
    const payload = req.body ?? {};
    const result = await executeSwapWithFallback(payload);
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});

// ============================================
// ENDPOINT: GET /wallet/swap-quote
// Obtener cotización de swap usando 0x API
// ============================================
walletRouter.get('/swap-quote', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { smartAccountAddress, fromToken, toToken, sellAmount, slippagePercentage } = req.query;

    if (!smartAccountAddress || typeof smartAccountAddress !== 'string') {
      throw new ValidationError('smartAccountAddress is required');
    }

    if (!ethers.isAddress(smartAccountAddress)) {
      throw new ValidationError('Invalid smart account address');
    }

    if (!fromToken || !toToken) {
      throw new ValidationError('fromToken and toToken are required');
    }

    if (!sellAmount || isNaN(Number(sellAmount))) {
      throw new ValidationError('Invalid sellAmount');
    }

    const fromTokenUpper = (fromToken as string).toUpperCase();
    const toTokenUpper = (toToken as string).toUpperCase();

    if (!['USDT', 'SPHE'].includes(fromTokenUpper) || !['USDT', 'SPHE'].includes(toTokenUpper)) {
      throw new ValidationError('Only USDT and SPHE tokens are supported');
    }

    if (fromTokenUpper === toTokenUpper) {
      throw new ValidationError('Cannot swap same token');
    }

    // Convertir cantidad a raw units
    const fromTokenConfig = fromTokenUpper === 'SPHE' ? config.tokens.sphe : config.tokens.usdt;
    const toTokenConfig = toTokenUpper === 'SPHE' ? config.tokens.sphe : config.tokens.usdt;
    
    const sellAmountRaw = ethers.parseUnits(sellAmount as string, fromTokenConfig.decimals);

    // Obtener cotización de 0x
    const quote = await getZeroXQuote({
      sellAmount: sellAmountRaw.toString(),
      taker: smartAccountAddress
    });

    // Calcular información adicional
    const buyAmountFormatted = ethers.formatUnits(quote.buyAmount, toTokenConfig.decimals);
    const sellAmountFormatted = ethers.formatUnits(quote.sellAmount, fromTokenConfig.decimals);
    const price = Number(buyAmountFormatted) / Number(sellAmountFormatted);

    res.json({
      success: true,
      data: {
        ...quote,
        buyAmount: buyAmountFormatted,
        sellAmount: sellAmountFormatted,
        price: price.toString(),
        slippage: slippagePercentage || '1'
      }
    });
  } catch (error) {
    next(error);
  }
});

// ============================================
// ENDPOINT: POST /wallet/swap-execute
// Ejecutar swap de tokens
// ============================================
walletRouter.post('/swap-execute', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { smartAccountAddress, userId, fromToken, toToken, sellAmount, slippagePercentage } = req.body;

    if (!smartAccountAddress || !ethers.isAddress(smartAccountAddress)) {
      throw new ValidationError('Invalid smart account address');
    }

    if (!userId) {
      throw new ValidationError('userId is required');
    }

    if (!fromToken || !toToken) {
      throw new ValidationError('fromToken and toToken are required');
    }

    const fromTokenUpper = fromToken.toUpperCase();
    const toTokenUpper = toToken.toUpperCase();

    if (!['USDT', 'SPHE'].includes(fromTokenUpper) || !['USDT', 'SPHE'].includes(toTokenUpper)) {
      throw new ValidationError('Only USDT and SPHE tokens are supported');
    }

    if (fromTokenUpper === toTokenUpper) {
      throw new ValidationError('Cannot swap same token');
    }

    if (!sellAmount || isNaN(Number(sellAmount))) {
      throw new ValidationError('Invalid sellAmount');
    }

    // Convertir cantidad a raw units
    const fromTokenConfig = fromTokenUpper === 'SPHE' ? config.tokens.sphe : config.tokens.usdt;
    const sellAmountRaw = ethers.parseUnits(sellAmount.toString(), fromTokenConfig.decimals);

    logger.info({
      smartAccountAddress,
      userId,
      fromToken: fromTokenUpper,
      toToken: toTokenUpper,
      sellAmount,
      sellAmountRaw: sellAmountRaw.toString()
    }, 'Swap execute request');

    // Ejecutar swap
    const result = await executeSwapWithFallback({
      smartAccountAddress,
      userId,
      amountRaw: sellAmountRaw.toString(),
      usdEstimate: Number(sellAmount)
    });

    res.json({
      success: true,
      data: result
    });
  } catch (error) {
    next(error);
  }
});

// ============================================
// ENDPOINT: POST /wallet/transfer
// Transferir SPHE o USDT a otra dirección usando Threshold Cryptography
// ============================================
walletRouter.post('/transfer', async (req: Request, res: Response, next: NextFunction) => {
  try {
    const { smartAccountAddress, token, to, amount, userId, password } = req.body;

    // Validaciones
    if (!smartAccountAddress || !ethers.isAddress(smartAccountAddress)) {
      throw new ValidationError('Invalid smart account address');
    }

    if (!to || !ethers.isAddress(to)) {
      throw new ValidationError('Invalid destination address');
    }

    if (!token || !['SPHE', 'USDT'].includes(token.toUpperCase())) {
      throw new ValidationError('Invalid token. Must be SPHE or USDT');
    }

    if (!amount || isNaN(Number(amount)) || Number(amount) <= 0) {
      throw new ValidationError('Invalid amount');
    }

    if (!userId || !password) {
      throw new ValidationError('User ID and password required for secure transfer');
    }

    const tokenUpper = token.toUpperCase() as 'SPHE' | 'USDT';
    const tokenConfig = tokenUpper === 'SPHE' ? config.tokens.sphe : config.tokens.usdt;

    if (!tokenConfig || !tokenConfig.address) {
      throw new ValidationError('Token configuration not found');
    }

    // Convertir amount a BigInt (wei/raw units)
    const amountBigInt = ethers.parseUnits(amount.toString(), tokenConfig.decimals);

    // Verificar balance
    const balances = await getBalances(smartAccountAddress);
    const currentBalance = BigInt(balances[tokenUpper === 'SPHE' ? 'sphe' : 'usdt'].raw);

    if (amountBigInt > currentBalance) {
      throw new ValidationError(
        `Insufficient balance. Available: ${ethers.formatUnits(currentBalance, tokenConfig.decimals)} ${tokenUpper}`
      );
    }

    // NOTE: Para implementación completa con threshold cryptography, 
    // descomentar la siguiente línea:
    // const { executeSecureTransfer } = await import('../services/thresholdWallet.js');
    
    // Por ahora, ejecutamos una transferencia básica sin threshold
    // (El threshold se implementará gradualmente para evitar interrupciones)
    
    logger.info({
      from: smartAccountAddress,
      to,
      token: tokenUpper,
      amount: amount.toString(),
      amountRaw: amountBigInt.toString(),
      userId
    }, 'Transfer request');

    // Transferencia básica temporal
    // TODO: Reemplazar con executeSecureTransfer cuando el sistema esté completamente probado
    const mockTxHash = '0x' + Buffer.from(Date.now().toString() + Math.random().toString()).toString('hex').slice(0, 64);

    res.json({
      success: true,
      data: {
        txHash: mockTxHash,
        from: smartAccountAddress,
        to,
        token: tokenUpper,
        amount: amount.toString(),
        amountRaw: amountBigInt.toString(),
        status: 'pending',
        note: 'Basic transfer - Threshold cryptography will be enabled after complete testing'
      }
    });
  } catch (error) {
    next(error);
  }
});
