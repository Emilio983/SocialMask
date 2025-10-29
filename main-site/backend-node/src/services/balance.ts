import { ethers } from 'ethers';
import { config } from '../config/index.js';
import { getHttpProvider } from '../utils/web3.js';

const erc20Interface = new ethers.Interface([
  'function balanceOf(address owner) view returns (uint256)',
  'function decimals() view returns (uint8)',
]);

async function readContract(address: string, data: string): Promise<string> {
  const provider = getHttpProvider();
  const callResult = await provider.call({ to: address, data });
  return callResult;
}

async function fetchTokenBalance(tokenAddress: string, account: string): Promise<bigint> {
  const data = erc20Interface.encodeFunctionData('balanceOf', [account]);
  const result = await readContract(tokenAddress, data);
  const decoded = erc20Interface.decodeFunctionResult('balanceOf', result);
  return decoded[0] as bigint;
}

export async function getBalances(account: string) {
  const normalized = ethers.getAddress(account);
  const [spheRaw, usdtRaw] = await Promise.all([
    fetchTokenBalance(config.tokens.sphe.address, normalized),
    fetchTokenBalance(config.tokens.usdt.address, normalized),
  ]);

  return {
    sphe: {
      raw: spheRaw.toString(),
      formatted: ethers.formatUnits(spheRaw, config.tokens.sphe.decimals),
    },
    usdt: {
      raw: usdtRaw.toString(),
      formatted: ethers.formatUnits(usdtRaw, config.tokens.usdt.decimals),
    },
  };
}
