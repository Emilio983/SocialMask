import axios from 'axios';
import { z } from 'zod';
import { config } from '../config/index.js';
import { ValidationError } from '../utils/errors.js';
import { logger } from '../utils/logger.js';
import { getHttpProvider } from '../utils/web3.js';
import { ethers } from 'ethers';
import { checkGasSponsorshipLimits, registerSponsoredTx } from './limits.js';
import { getBalances } from './balance.js';
import { submitSponsoredCall } from './userOperation.js';
import { recordSwapEvent } from './swapEvents.js';

const quoteSchema = z.object({
  sellAmount: z.string().min(1),
  taker: z.string().regex(/^0x[a-fA-F0-9]{40}$/),
});

export type SwapQuote = {
  price: string;
  guaranteedPrice: string;
  to: string;
  data: string;
  value: string;
  gas: string;
  estimatedGas: string;
  sellAmount: string;
  buyAmount: string;
};

export async function getZeroXQuote(params: unknown): Promise<SwapQuote> {
  const parsed = quoteSchema.safeParse(params);
  if (!parsed.success) {
    throw new ValidationError('Invalid swap quote payload', { issues: parsed.error.issues });
  }

  const { sellAmount, taker } = parsed.data;

  const response = await axios.get<SwapQuote>(config.swaps.apiUrl, {
    params: {
      sellToken: config.tokens.usdt.address,
      buyToken: config.tokens.sphe.address,
      sellAmount,
      takerAddress: taker,
      slippagePercentage: config.swaps.slippageBps / 10_000,
    },
    headers: {
      '0x-api-key': config.swaps.zeroXApiKey,
    },
  });

  return response.data;
}

type ExecuteSwapPayload = {
  smartAccountAddress: string;
  userId: number;
  amountRaw?: string | number | bigint;
  usdEstimate?: number | string;
  depositTxHash?: string;
  depositAddress?: string;
};

export async function executeSwapWithFallback(rawPayload: Record<string, unknown>) {
  const payload = rawPayload as ExecuteSwapPayload;
  const smartAccountAddress = payload.smartAccountAddress;
  const userId = payload.userId;
  const depositTxHash = payload.depositTxHash;
  const depositAddress = payload.depositAddress;

  if (!smartAccountAddress) {
    throw new ValidationError('smartAccountAddress required');
  }

  if (!userId) {
    throw new ValidationError('userId required');
  }

  logger.info({ smartAccountAddress, depositTxHash }, 'executeSwapWithFallback invoked');

  const limits = await checkGasSponsorshipLimits(userId);
  if (!limits.allowed) {
    throw new ValidationError('Gas sponsor limit exceeded');
  }

  const balances = await getBalances(smartAccountAddress);
  const amountRaw = payload?.amountRaw !== undefined ? BigInt(String(payload.amountRaw)) : BigInt(balances.usdt.raw);

  if (amountRaw <= 0n) {
    throw new ValidationError('No hay USDT disponible para convertir');
  }

  const usdEstimate =
    typeof payload?.usdEstimate === 'number'
      ? payload.usdEstimate
      : parseFloat(String(payload?.usdEstimate ?? ethers.formatUnits(amountRaw, config.tokens.usdt.decimals)));

  try {
    const quote = await getZeroXQuote({
      sellAmount: amountRaw.toString(),
      taker: smartAccountAddress,
    });

    const tx = await submitSponsoredCall({ target: quote.to, data: quote.data, value: quote.value });

    await registerSponsoredTx(userId, usdEstimate || 0.25);

    const eventId = await recordSwapEvent({
      userId,
      smartAccountAddress,
      aggregator: '0X',
      amountUsdt: ethers.formatUnits(amountRaw, config.tokens.usdt.decimals),
      amountSpheEstimate: ethers.formatUnits(BigInt(quote.buyAmount), config.tokens.sphe.decimals),
      slippageBps: config.swaps.slippageBps,
      taskId: tx.taskId,
      depositTxHash,
      depositAddress,
    });

    return {
      status: 'submitted',
      aggregator: '0X',
      taskId: tx.taskId,
      buyAmount: quote.buyAmount,
      swapEventId: eventId,
    };
  } catch (error) {
    logger.warn({ error }, '0x swap failed, trying fallback');
  }

  if (config.swaps.fallbackPreferred === 'QUICKSWAP') {
    const fallback = await buildQuickSwapFallback(amountRaw, smartAccountAddress, balances);
    const tx = await submitSponsoredCall({ target: fallback.target, data: fallback.data, value: fallback.value });
    await registerSponsoredTx(userId, usdEstimate || 0.25);
    const eventId = await recordSwapEvent({
      userId,
      smartAccountAddress,
      aggregator: 'QUICKSWAP',
      amountUsdt: ethers.formatUnits(amountRaw, config.tokens.usdt.decimals),
      amountSpheEstimate: ethers.formatUnits(BigInt(fallback.minAmountOut), config.tokens.sphe.decimals),
      slippageBps: config.swaps.slippageBps,
      taskId: tx.taskId,
      depositTxHash,
      depositAddress,
    });
    return {
      status: 'submitted',
      aggregator: 'QUICKSWAP',
      taskId: tx.taskId,
      amountIn: fallback.amountIn,
      minAmountOut: fallback.minAmountOut,
      swapEventId: eventId,
    };
  }

  const uniswap = await buildUniswapFallback(amountRaw, smartAccountAddress, balances);
  const tx = await submitSponsoredCall({ target: uniswap.target, data: uniswap.data, value: uniswap.value });
  await registerSponsoredTx(userId, usdEstimate || 0.25);
  const eventId = await recordSwapEvent({
    userId,
    smartAccountAddress,
    aggregator: 'UNISWAP',
    amountUsdt: ethers.formatUnits(amountRaw, config.tokens.usdt.decimals),
    amountSpheEstimate: ethers.formatUnits(BigInt(uniswap.minAmountOut), config.tokens.sphe.decimals),
    slippageBps: config.swaps.slippageBps,
    taskId: tx.taskId,
    depositTxHash,
    depositAddress,
  });
  return {
    status: 'submitted',
    aggregator: 'UNISWAP',
    taskId: tx.taskId,
    amountIn: uniswap.amountIn,
    minAmountOut: uniswap.minAmountOut,
    swapEventId: eventId,
  };
}

const quickswapInterface = new ethers.Interface([
  'function getAmountsOut(uint256 amountIn, address[] calldata path) view returns (uint256[] memory amounts)',
  'function swapExactTokensForTokens(uint256 amountIn, uint256 amountOutMin, address[] calldata path, address to, uint256 deadline) returns (uint256[] memory amounts)',
]);

async function buildQuickSwapFallback(amountRaw: bigint, smartAccount: string, balances: { usdt: { raw: string } }) {
  const provider = getHttpProvider();
  const router = config.swaps.quickswapRouter;
  const amountIn = amountRaw ?? BigInt(balances.usdt.raw);
  if (!amountIn || amountIn <= 0n) {
    throw new ValidationError('Sin USDT para convertir');
  }

  const path = [config.tokens.usdt.address, config.tokens.sphe.address];
  const getAmountsData = quickswapInterface.encodeFunctionData('getAmountsOut', [amountIn, path]);
  const result = await provider.call({ to: router, data: getAmountsData });
  const decoded = quickswapInterface.decodeFunctionResult('getAmountsOut', result);
  const amounts = decoded[0] as bigint[];
  const outRaw = amounts[amounts.length - 1];

  const slippageBps = BigInt(config.swaps.slippageBps ?? 50);
  const amountOutMin = outRaw - (outRaw * slippageBps) / 10_000n;
  const deadline = Math.floor(Date.now() / 1000) + 600;

  const callData = quickswapInterface.encodeFunctionData('swapExactTokensForTokens', [
    amountIn,
    amountOutMin,
    path,
    smartAccount,
    deadline,
  ]);

  return {
    status: 'pending-integration',
    aggregator: 'QUICKSWAP',
    target: router,
    data: callData,
    value: '0x0',
    minAmountOut: amountOutMin.toString(),
    amountIn: amountIn.toString(),
  };
}

async function buildUniswapFallback(amountRaw: bigint, smartAccount: string, balances: { usdt: { raw: string } }) {
  const quickswapFallback = await buildQuickSwapFallback(amountRaw, smartAccount, balances);
  return {
    ...quickswapFallback,
    aggregator: 'UNISWAP',
    note: 'Placeholder: utilizando ruta QuickSwap mientras se integra Uniswap V3',
  };
}
