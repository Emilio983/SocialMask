import { RowDataPacket } from 'mysql2/promise';
import { ethers } from 'ethers';
import { config } from '../config/index.js';
import { getHttpProvider } from '../utils/web3.js';
import { logger } from '../utils/logger.js';
import { submitSponsoredCall, getRelayTaskStatus } from './userOperation.js';
import { pool } from '../db/index.js';

const SIMPLE_ACCOUNT_FACTORY_ABI = [
  'function getAddress(address owner, uint256 salt) view returns (address)',
  'function createAccount(address owner, uint256 salt) returns (address)',
];

const ACCOUNT_DEPLOYMENT_SALT = config.smartAccounts.defaultSalt ?? 0n;
const DEPLOYMENT_POLL_INTERVAL_MS = 4_000;
const DEPLOYMENT_POLL_ATTEMPTS = 6;

const factoryInterface = new ethers.Interface(SIMPLE_ACCOUNT_FACTORY_ABI);

export type SmartAccountParams = {
  ownerAddress: string;
  devicePublicKey?: string;
};

export type SmartAccountDeployment = {
  smartAccountAddress: string;
  deploymentTxHash: string | null;
  status: 'pending' | 'deployed' | 'deploying';
  paymasterPolicyId: string | null;
};

export type SmartAccountDbRecord = {
  userId: number;
  smartAccountAddress: string;
  deploymentTxHash: string | null;
  status: string;
  paymasterPolicyId: string | null;
  ownerAddress: string;
  username: string;
  alias: string | null;
};

export type UserDbRecord = {
  userId: number;
  walletAddress: string;
  username: string;
  alias: string | null;
  smartAccountAddress: string | null;
};

function mapSmartAccountRow(row: RowDataPacket): SmartAccountDbRecord {
  return {
    userId: Number(row.user_id),
    smartAccountAddress: row.smart_account_address,
    deploymentTxHash: row.deployment_tx_hash ?? null,
    status: row.status,
    paymasterPolicyId: row.paymaster_policy_id ?? null,
    ownerAddress: row.wallet_address,
    username: row.username,
    alias: row.alias ?? null,
  };
}

function mapUserRow(row: RowDataPacket): UserDbRecord {
  return {
    userId: Number(row.user_id),
    walletAddress: row.wallet_address,
    username: row.username,
    alias: row.alias ?? null,
    smartAccountAddress: row.smart_account_address ?? null,
  };
}

async function fetchSmartAccountRecord(whereClause: string, params: unknown[]): Promise<SmartAccountDbRecord | null> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT sa.user_id,
            sa.smart_account_address,
            sa.deployment_tx_hash,
            sa.status,
            sa.paymaster_policy_id,
            u.wallet_address,
            u.username,
            u.alias
     FROM smart_accounts sa
     JOIN users u ON u.user_id = sa.user_id
     WHERE ${whereClause}
     LIMIT 1`,
    params,
  );

  if (!rows.length) {
    return null;
  }

  return mapSmartAccountRow(rows[0]);
}

async function fetchUserRecord(whereClause: string, params: unknown[]): Promise<UserDbRecord | null> {
  const [rows] = await pool.execute<RowDataPacket[]>(
    `SELECT user_id, wallet_address, username, alias, smart_account_address
     FROM users
     WHERE ${whereClause}
     LIMIT 1`,
    params,
  );

  if (!rows.length) {
    return null;
  }

  return mapUserRow(rows[0]);
}
/**
 * Calcula la dirección counterfactual de una smart account ERC-4337
 * usando CREATE2 con el factory de Gelato
 *
 * La dirección se puede calcular antes del deploy usando:
 * - Factory address (Gelato Simple Account Factory)
 * - Owner address (EOA que controla la cuenta)
 * - Salt (nonce, generalmente 0 para primera cuenta)
 */
async function getCounterfactualAddress(ownerAddress: string): Promise<string> {
  const provider = getHttpProvider();
  
  try {
    // Usar staticCall para llamar a la función view del factory
    const calldata = factoryInterface.encodeFunctionData('getAddress', [ownerAddress, ACCOUNT_DEPLOYMENT_SALT]);
    const result = await provider.call({
      to: config.smartAccounts.factory,
      data: calldata,
    });
    const [smartAddress] = factoryInterface.decodeFunctionResult('getAddress', result);
    return ethers.getAddress(smartAddress);
  } catch (error) {
    logger.warn({ err: error, ownerAddress }, 'Falling back to manual counterfactual derivation');

    const seed = ethers.keccak256(
      ethers.concat([
        ethers.toUtf8Bytes('sphoria-smart-account'),
        ethers.toUtf8Bytes(ownerAddress.toLowerCase()),
      ]),
    );
    return ethers.getAddress(`0x${seed.slice(-40)}`);
  }
}

export async function findSmartAccountByUserId(userId: number): Promise<SmartAccountDbRecord | null> {
  if (!userId) {
    return null;
  }
  return fetchSmartAccountRecord('sa.user_id = ?', [userId]);
}

export async function findSmartAccountByOwner(ownerAddress: string): Promise<SmartAccountDbRecord | null> {
  return fetchSmartAccountRecord('LOWER(u.wallet_address) = ?', [ownerAddress.toLowerCase()]);
}

export async function findSmartAccountByAddress(smartAccountAddress: string): Promise<SmartAccountDbRecord | null> {
  return fetchSmartAccountRecord('LOWER(sa.smart_account_address) = ?', [smartAccountAddress.toLowerCase()]);
}

export async function findUserById(userId: number): Promise<UserDbRecord | null> {
  if (!userId) {
    return null;
  }
  return fetchUserRecord('user_id = ?', [userId]);
}

export async function findUserByOwner(ownerAddress: string): Promise<UserDbRecord | null> {
  return fetchUserRecord('LOWER(wallet_address) = ?', [ownerAddress.toLowerCase()]);
}

export async function predictSmartAccountAddress(ownerAddress: string): Promise<string> {
  return getCounterfactualAddress(ownerAddress);
}

/**
 * Verifica si una smart account ya está desplegada on-chain
 */
async function isAccountDeployed(address: string): Promise<boolean> {
  try {
    const provider = getHttpProvider();
    const code = await provider.getCode(address);

    // Si hay código en la dirección, está desplegada
    return code !== '0x' && code !== '0x0';
  } catch (error) {
    logger.error({ err: error, address }, 'Error checking if account is deployed');
    return false;
  }
}

/**
 * Crea o verifica una smart account ERC-4337 para un usuario
 *
 * Flujo:
 * 1. Calcula la dirección counterfactual (determinística)
 * 2. Verifica si ya está desplegada on-chain
 * 3. Si NO está desplegada, retorna status 'pending' (se desplegará en la primera tx)
 * 4. Si está desplegada, retorna status 'deployed'
 *
 * NOTA: El deploy real se hace automáticamente en la primera UserOperation
 * gracias al initCode que se incluye en el UserOp
 */
function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForDeployment(taskId: string, smartAccountAddress: string) {
  for (let attempt = 0; attempt < DEPLOYMENT_POLL_ATTEMPTS; attempt += 1) {
    await delay(DEPLOYMENT_POLL_INTERVAL_MS);

    try {
      const status = await getRelayTaskStatus(taskId);
      if (!status) {
        continue;
      }

      const state = status.taskState;

      if (state === 'ExecSuccess') {
        return {
          status: 'deployed' as const,
          txHash: status.transactionHash ?? null,
        };
      }

      if (state === 'ExecReverted' || state === 'Cancelled') {
        logger.error({ taskId, state }, 'Smart account deployment failed');
        return {
          status: 'pending' as const,
          txHash: status.transactionHash ?? null,
        };
      }
    } catch (error) {
      logger.warn({ error, taskId }, 'Failed to fetch Gelato task status for deployment');
    }
  }

  const deployed = await isAccountDeployed(smartAccountAddress);
  if (deployed) {
    return {
      status: 'deployed' as const,
      txHash: null,
    };
  }

  return {
    status: 'deploying' as const,
    txHash: null,
  };
}

export async function upsertSmartAccountRecord(userId: number, data: SmartAccountDeployment): Promise<void> {
  const dbStatus = data.status === 'deployed' ? 'deployed' : 'pending';

  await pool.execute(
    `INSERT INTO smart_accounts (user_id, smart_account_address, deployment_tx_hash, paymaster_policy_id, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, NOW(), NOW())
     ON DUPLICATE KEY UPDATE smart_account_address = VALUES(smart_account_address),
                             deployment_tx_hash = VALUES(deployment_tx_hash),
                             paymaster_policy_id = VALUES(paymaster_policy_id),
                             status = VALUES(status),
                             updated_at = NOW())`,
    [
      userId,
      data.smartAccountAddress,
      data.deploymentTxHash ?? null,
      data.paymasterPolicyId ?? null,
      dbStatus,
    ],
  );

  await pool.execute('UPDATE users SET smart_account_address = ?, updated_at = NOW() WHERE user_id = ?', [data.smartAccountAddress, userId]);
}

export async function ensureSmartAccount(params: SmartAccountParams): Promise<SmartAccountDeployment> {
  const paymasterPolicyId = config.gelato.policyId || null;

  try {
    logger.debug({ owner: params.ownerAddress }, 'Creating smart account');

    // Normalizar owner address
    const ownerAddress = ethers.getAddress(params.ownerAddress);

    // Calcular dirección counterfactual (predecible antes del deploy)
    const smartAccountAddress = await getCounterfactualAddress(ownerAddress);

    logger.info({
      owner: ownerAddress,
      smartAccount: smartAccountAddress
    }, 'Smart account address calculated');

    // Verificar si ya está desplegada
    const isDeployed = await isAccountDeployed(smartAccountAddress);

    if (isDeployed) {
      logger.info({ smartAccount: smartAccountAddress }, 'Smart account already deployed');
      return {
        smartAccountAddress,
        deploymentTxHash: null, // No sabemos el hash original
        status: 'deployed',
        paymasterPolicyId,
      };
    }

    logger.info({ smartAccount: smartAccountAddress, owner: ownerAddress }, 'Smart account not deployed, triggering deployment via Gelato');

    const data = factoryInterface.encodeFunctionData('createAccount', [ownerAddress, ACCOUNT_DEPLOYMENT_SALT]);

    try {
      const { taskId } = await submitSponsoredCall({
        target: config.smartAccounts.factory,
        data,
      });

      logger.info({ taskId, owner: ownerAddress, smartAccount: smartAccountAddress }, 'Smart account deployment task submitted');

      const deployment = await waitForDeployment(taskId, smartAccountAddress);

      return {
        smartAccountAddress,
        deploymentTxHash: deployment.txHash,
        status: deployment.status,
        paymasterPolicyId,
      };
    } catch (error) {
      logger.error({ err: error, owner: ownerAddress }, 'Failed to deploy smart account via Gelato');
      return {
        smartAccountAddress,
        deploymentTxHash: null,
        status: 'pending',
        paymasterPolicyId,
      };
    }

  } catch (error) {
    logger.error({ err: error, params }, 'Error ensuring smart account');

    // Fallback: crear dirección determinística simple
    const seed = ethers.keccak256(ethers.toUtf8Bytes(params.ownerAddress.toLowerCase()));
    const pseudoAddress = ethers.getAddress(`0x${seed.slice(-40)}`);

    return {
      smartAccountAddress: pseudoAddress,
      deploymentTxHash: null,
      status: 'pending',
      paymasterPolicyId,
    };
  }
}

/**
 * Obtiene el initCode para desplegar una smart account
 * Este código se incluye en la primera UserOperation para hacer deploy
 */
export function getInitCode(ownerAddress: string): string {
  // Función: createAccount(address owner, uint256 salt)
  const func = factoryInterface.getFunction('createAccount');
  if (!func) {
    throw new Error('createAccount function not found in factory ABI');
  }
  const functionSignature = func.selector;

  const encodedParams = ethers.AbiCoder.defaultAbiCoder().encode(
    ['address', 'uint256'],
    [ownerAddress, ACCOUNT_DEPLOYMENT_SALT],
  );

  return config.smartAccounts.factory + functionSignature.slice(2) + encodedParams.slice(2);
}

/**
 * Verifica el status de una smart account
 */
export async function getSmartAccountStatus(address: string): Promise<SmartAccountDeployment> {
  const isDeployed = await isAccountDeployed(address);

  return {
    smartAccountAddress: address,
    deploymentTxHash: null,
    status: isDeployed ? 'deployed' : 'pending',
    paymasterPolicyId: config.gelato.policyId || null,
  };
}

/**
 * Sponsor y ejecuta una UserOperation usando Gelato Relay
 */
export async function sponsorUserOperation(userOp: Record<string, unknown>) {
  logger.debug({ userOp }, 'Sponsoring UserOperation via Gelato');

  try {
    // TODO: Implementar con Gelato Relay SDK v5
    // const result = await relay.sponsoredCall({
    //   chainId: BigInt(config.network.chainId),
    //   target: userOp.target,
    //   data: userOp.data,
    //   user: userOp.sender,
    //   sponsorApiKey: config.gelato.relayApiKey,
    // });

    return {
      relayTaskId: 'pending-integration',
      userOperation: userOp,
    };
  } catch (error) {
    logger.error({ err: error }, 'Error sponsoring UserOperation');
    throw error;
  }
}
