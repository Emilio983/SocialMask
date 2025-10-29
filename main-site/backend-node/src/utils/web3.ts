import { ethers } from 'ethers';
import { config } from '../config/index.js';

let providerHttp: ethers.JsonRpcProvider | null = null;
let providerWs: ethers.WebSocketProvider | null = null;

export function getHttpProvider(): ethers.JsonRpcProvider {
  if (!providerHttp) {
    providerHttp = new ethers.JsonRpcProvider(config.polygonRpc.http, {
      name: config.network,
      chainId: config.chainId,
    });
  }
  return providerHttp;
}

export function getWsProvider(): ethers.WebSocketProvider {
  if (!providerWs) {
    providerWs = new ethers.WebSocketProvider(config.polygonRpc.wss, config.chainId);
    providerWs.on('error', (error) => {
      // eslint-disable-next-line no-console
      console.error('WS provider error', error);
    });
    // Set max listeners if method exists
    if (typeof (providerWs as any).setMaxListeners === 'function') {
      (providerWs as any).setMaxListeners(20);
    }
  }
  return providerWs;
}

export function toWei(amount: string | number | bigint, decimals: number): bigint {
  return ethers.parseUnits(amount.toString(), decimals);
}

export function fromWei(amount: bigint, decimals: number): string {
  return ethers.formatUnits(amount, decimals);
}
