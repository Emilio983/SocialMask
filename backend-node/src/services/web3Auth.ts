import { createRemoteJWKSet, jwtVerify, JWTPayload } from 'jose';
import { ethers } from 'ethers';
import { config } from '../config/index.js';
import { ValidationError } from '../utils/errors.js';
import { logger } from '../utils/logger.js';

type Web3AuthWalletClaim = {
  type?: string;
  curve?: string;
  address?: string;
  public_key?: string;
  private_key?: string;
};

type Web3AuthTokenPayload = JWTPayload & {
  wallets?: Web3AuthWalletClaim[];
  wallet?: Web3AuthWalletClaim;
  verifier?: string;
  verifierId?: string;
  verifier_id?: string;
  private_key?: string;
  privateKey?: string;
};

type Web3AuthVerificationResult = {
  ownerAddress: string;
  privateKey: string;
  verifier?: string;
  verifierId?: string;
  rawPayload: Web3AuthTokenPayload;
};

let jwks: ReturnType<typeof createRemoteJWKSet> | null = null;

function getJwks() {
  if (!jwks) {
    jwks = createRemoteJWKSet(new URL(config.web3Auth.jwksEndpoint));
  }
  return jwks;
}

function normalizeHex(value: string): string {
  return value.startsWith('0x') ? value.slice(2) : value;
}

function hexFromMaybeBase64(value?: string): string | null {
  if (!value) return null;
  const trimmed = value.trim();

  const normalized = normalizeHex(trimmed);
  if (/^[0-9a-fA-F]{64}$/.test(normalized)) {
    return `0x${normalized.toLowerCase()}`;
  }

  try {
    const buffer = Buffer.from(trimmed, 'base64');
    if (buffer.length === 32) {
      return `0x${buffer.toString('hex')}`;
    }
  } catch (error) {
    logger.debug({ err: error }, 'Failed to parse Web3Auth private key as base64');
  }

  return null;
}

function pickEvmWallet(payload: Web3AuthTokenPayload): Web3AuthWalletClaim | undefined {
  const wallets = Array.isArray(payload.wallets) ? payload.wallets : [];
  if (wallets.length) {
    const direct = wallets.find((wallet) => {
      const type = wallet.type?.toLowerCase();
      const curve = wallet.curve?.toLowerCase();
      return type === 'evm' || curve === 'secp256k1';
    });
    return direct ?? wallets[0];
  }

  if (payload.wallet) {
    return payload.wallet;
  }

  return undefined;
}

export async function verifyWeb3AuthIdToken(idToken: string): Promise<Web3AuthVerificationResult> {
  if (!idToken) {
    throw new ValidationError('Web3Auth idToken required');
  }

  const jwksKeySet = getJwks();

  let payload: Web3AuthTokenPayload;

  try {
    const verification = await jwtVerify(idToken, jwksKeySet);
    payload = verification.payload as Web3AuthTokenPayload;
  } catch (error) {
    throw new ValidationError('Invalid Web3Auth token', { cause: error });
  }

  const wallet = pickEvmWallet(payload);

  const extractedPrivateKey =
    hexFromMaybeBase64(wallet?.private_key) ??
    hexFromMaybeBase64(payload.private_key) ??
    hexFromMaybeBase64(payload.privateKey);

  if (!extractedPrivateKey) {
    throw new ValidationError('Web3Auth token missing EVM private key');
  }

  let ownerAddress: string;
  try {
    ownerAddress = ethers.getAddress(ethers.computeAddress(extractedPrivateKey));
  } catch (error) {
    throw new ValidationError('Web3Auth private key invalid', { cause: error });
  }

  const verifier =
    payload.verifier ??
    (typeof payload.iss === 'string' ? payload.iss : undefined);
  const verifierId =
    payload.verifierId ??
    payload.verifier_id ??
    (typeof payload.sub === 'string' ? payload.sub : undefined);

  return {
    ownerAddress,
    privateKey: extractedPrivateKey,
    verifier,
    verifierId,
    rawPayload: payload,
  };
}
