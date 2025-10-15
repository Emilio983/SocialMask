import { ethers } from 'ethers';
import { getWsProvider } from '../utils/web3.js';
import { config } from '../config/index.js';
import { logger } from '../utils/logger.js';
import { processUsdtDeposit } from '../services/depositProcessor.js';

export function startDepositWatcher() {
  const provider = getWsProvider();
  logger.info('Starting USDT deposit watcher');

  const transferTopic = ethers.id('Transfer(address,address,uint256)');
  const erc20Interface = new ethers.Interface(['event Transfer(address indexed from, address indexed to, uint256 value)']);

  provider.on(
    {
      address: config.tokens.usdt.address,
      topics: [transferTopic],
    },
    async (log) => {
      try {
        const parsed = erc20Interface.parseLog(log);
        if (!parsed) {
          return;
        }
        const from = parsed.args.from as string;
        const to = parsed.args.to as string;
        const value = parsed.args.value as bigint;

        await processUsdtDeposit({
          from,
          to,
          value,
          txHash: log.transactionHash,
          blockNumber: Number(log.blockNumber ?? 0),
        });
      } catch (error) {
        logger.error({ error, log }, 'Failed to handle deposit log');
      }
    },
  );

  return provider;
}
