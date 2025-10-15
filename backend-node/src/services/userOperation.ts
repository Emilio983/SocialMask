import { GelatoRelay, ERC2771Type, CallWithERC2771Request } from '@gelatonetwork/relay-sdk';
import { ethers } from 'ethers';
import { config } from '../config/index.js';
import { logger } from '../utils/logger.js';
import { AppError } from '../utils/errors.js';

export const relay = new GelatoRelay();

type SponsoredCallInput = {
  target: string;
  data: string;
  value?: string;
};

export async function submitSponsoredCall({ target, data, value = '0' }: SponsoredCallInput) {
  try {
    logger.info({ target }, 'Submitting sponsored call to Gelato');
    const response = await relay.sponsoredCall(
      {
        chainId: BigInt(config.chainId),
        target,
        data,
      },
      config.gelato.apiKey,
    );

    return {
      taskId: response.taskId,
    };
  } catch (error: any) {
    logger.error({ error }, 'Gelato sponsored call failed');
    throw new AppError(502, 'gelato_failure', error?.message || 'Gelato relay failed');
  }
}

export async function getRelayTaskStatus(taskId: string) {
  try {
    const status = await relay.getTaskStatus(taskId);
    return status;
  } catch (error: any) {
    logger.error({ error, taskId }, 'Failed to fetch Gelato task status');
    throw new AppError(502, 'gelato_status_error', error?.message || 'Gelato task status failed');
  }
}

type SponsoredCallERC2771Input = {
  wallet: ethers.Wallet;
  target: string;
  data: string;
  gasLimit?: bigint;
  userDeadlineSeconds?: number;
};

export async function submitSponsoredCallERC2771({
  wallet,
  target,
  data,
  gasLimit,
  userDeadlineSeconds = 3600,
}: SponsoredCallERC2771Input) {
  try {
    const request: CallWithERC2771Request = {
      chainId: BigInt(config.chainId),
      target: ethers.getAddress(target),
      data,
      user: wallet.address,
      userDeadline: Math.floor(Date.now() / 1000) + userDeadlineSeconds,
    };

    if (gasLimit) {
      (request as CallWithERC2771Request & { gasLimit: bigint }).gasLimit = gasLimit;
    }

    const { struct, signature } = await relay.getSignatureDataERC2771(
      request,
      wallet as unknown as any,
      ERC2771Type.SponsoredCall,
    );

    const response = await relay.sponsoredCallERC2771WithSignature(
      struct,
      signature,
      config.gelato.apiKey,
    );

    return {
      taskId: response.taskId,
    };
  } catch (error: any) {
    logger.error({ error, user: wallet.address }, 'Gelato ERC-2771 sponsored call failed');
    throw new AppError(502, 'gelato_erc2771_failure', error?.message || 'Gelato ERC-2771 failed');
  }
}
