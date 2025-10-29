import { logger } from '../utils/logger.js';

type SwapJob = {
  smartAccount: string;
  depositTxHash: string;
  usdtAmount: string;
};

const queue: SwapJob[] = [];

export function enqueueSwap(job: SwapJob) {
  queue.push(job);
  logger.info({ job }, 'Swap job enqueued');
}

export async function processQueue() {
  while (queue.length > 0) {
    const job = queue.shift();
    if (!job) {
      return;
    }

    logger.info({ job }, 'Processing swap job');
    // TODO: call executeSwapWithFallback and persist results.
  }
}
