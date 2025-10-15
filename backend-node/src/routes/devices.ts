import { Router } from 'express';
import { ethers } from 'ethers';
import {
  ensureSmartAccount,
  getSmartAccountStatus,
  findSmartAccountByOwner,
  findSmartAccountByAddress,
  findSmartAccountByUserId,
  findUserById,
  findUserByOwner,
  upsertSmartAccountRecord,
  predictSmartAccountAddress,
} from '../services/smartAccount.js';
import {
  consumeDeviceLink,
  createDeviceLink,
  listDeviceLinks,
  listDevices,
  revokeDevice,
  validateDeviceLink,
} from '../services/deviceLink.js';
import { ValidationError } from '../utils/errors.js';

export const devicesRouter = Router();

// Legacy compatibility: ensure smart account exists for owner/device pair
devicesRouter.post('/link', async (req, res, next) => {
  try {
    const { ownerAddress, devicePublicKey } = req.body ?? {};
    const result = await ensureSmartAccount({ ownerAddress, devicePublicKey });
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});

devicesRouter.post('/link/start', async (req, res, next) => {
  try {
    const userId = Number(req.body?.userId ?? 0);
    const data = await createDeviceLink(userId);
    res.json({ success: true, data });
  } catch (error) {
    next(error);
  }
});

devicesRouter.get('/link/status', async (req, res, next) => {
  try {
    const userId = Number(req.query.userId ?? 0);
    const data = await listDeviceLinks(userId);
    res.json({ success: true, data });
  } catch (error) {
    next(error);
  }
});

devicesRouter.post('/link/validate', async (req, res, next) => {
  try {
    const { linkCode, qrToken } = req.body ?? {};
    const data = await validateDeviceLink(linkCode, qrToken);
    res.json({ success: true, data });
  } catch (error) {
    next(error);
  }
});

devicesRouter.post('/link/consume', async (req, res, next) => {
  try {
    const { linkCode, qrToken, devicePublicKey, credentialId, ownerAddress, deviceLabel, platform } = req.body ?? {};
    const data = await consumeDeviceLink({
      linkCode,
      qrToken,
      devicePublicKey,
      credentialId,
      ownerAddress,
      deviceLabel,
      platform,
    });
    res.json({ success: true, data });
  } catch (error) {
    next(error);
  }
});

devicesRouter.get('/smart-account/status', async (req, res, next) => {
  try {
    const ownerAddressRaw = typeof req.query.ownerAddress === 'string' ? req.query.ownerAddress.trim() : '';
    const smartAccountRaw = typeof req.query.smartAccountAddress === 'string' ? req.query.smartAccountAddress.trim() : '';
    const userIdRaw = typeof req.query.userId === 'string' ? req.query.userId : req.query.userId !== undefined ? String(req.query.userId) : undefined;

    let userId: number | undefined;
    if (userIdRaw && userIdRaw.trim().length > 0) {
      const parsed = Number(userIdRaw);
      if (!Number.isFinite(parsed) || parsed <= 0) {
        throw new ValidationError('userId must be a positive number');
      }
      userId = parsed;
    }

    let ownerAddress: string | undefined;
    if (ownerAddressRaw) {
      try {
        ownerAddress = ethers.getAddress(ownerAddressRaw);
      } catch (error) {
        throw new ValidationError('Invalid ownerAddress provided');
      }
    }

    let smartAccountAddress: string | undefined;
    if (smartAccountRaw) {
      try {
        smartAccountAddress = ethers.getAddress(smartAccountRaw);
      } catch (error) {
        throw new ValidationError('Invalid smartAccountAddress provided');
      }
    }

    if (!ownerAddress && !smartAccountAddress && !userId) {
      throw new ValidationError('Provide at least one of ownerAddress, smartAccountAddress or userId');
    }

    let dbRecord = userId ? await findSmartAccountByUserId(userId) : null;
    if (!dbRecord && smartAccountAddress) {
      dbRecord = await findSmartAccountByAddress(smartAccountAddress);
    }
    if (!dbRecord && ownerAddress) {
      dbRecord = await findSmartAccountByOwner(ownerAddress);
    }

    let userRecord = userId ? await findUserById(userId) : null;
    if (!userRecord && ownerAddress) {
      userRecord = await findUserByOwner(ownerAddress);
    }
    if (!userRecord && dbRecord) {
      userRecord = await findUserById(dbRecord.userId);
    }

    if (!ownerAddress) {
      const resolvedOwner = dbRecord?.ownerAddress ?? userRecord?.walletAddress;
      if (!resolvedOwner) {
        throw new ValidationError('Unable to resolve ownerAddress');
      }
      ownerAddress = ethers.getAddress(resolvedOwner);
    }

    if (!smartAccountAddress) {
      smartAccountAddress = dbRecord?.smartAccountAddress ?? userRecord?.smartAccountAddress ?? undefined;
    }

    if (!smartAccountAddress) {
      smartAccountAddress = await predictSmartAccountAddress(ownerAddress);
    }

    const chainStatus = await getSmartAccountStatus(smartAccountAddress);

    const payload = {
      ownerAddress,
      smartAccountAddress: chainStatus.smartAccountAddress,
      onChainStatus: chainStatus.status,
      deploymentTxHash: chainStatus.deploymentTxHash ?? dbRecord?.deploymentTxHash ?? null,
      paymasterPolicyId: dbRecord?.paymasterPolicyId ?? chainStatus.paymasterPolicyId ?? null,
      databaseStatus: dbRecord?.status ?? null,
      userId: dbRecord?.userId ?? userRecord?.userId ?? null,
      username: dbRecord?.username ?? userRecord?.username ?? null,
      alias: dbRecord?.alias ?? userRecord?.alias ?? null,
    };

    res.json({ success: true, data: payload });
  } catch (error) {
    next(error);
  }
});

devicesRouter.post('/smart-account/redeploy', async (req, res, next) => {
  try {
    const { userId: bodyUserId, ownerAddress: bodyOwnerAddress } = req.body ?? {};

    let userId: number | undefined;
    if (bodyUserId !== undefined && bodyUserId !== null) {
      const parsed = Number(bodyUserId);
      if (!Number.isFinite(parsed) || parsed <= 0) {
        throw new ValidationError('userId must be a positive number');
      }
      userId = parsed;
    }

    let ownerAddress: string | undefined;
    if (typeof bodyOwnerAddress === 'string' && bodyOwnerAddress.trim().length > 0) {
      try {
        ownerAddress = ethers.getAddress(bodyOwnerAddress);
      } catch (error) {
        throw new ValidationError('Invalid ownerAddress provided');
      }
    }

    let userRecord = userId ? await findUserById(userId) : null;

    if (!userRecord && ownerAddress) {
      userRecord = await findUserByOwner(ownerAddress);
    }

    if (!userRecord) {
      throw new ValidationError('User not found for provided identifiers');
    }

    userId = userRecord.userId;
    ownerAddress = ownerAddress ?? ethers.getAddress(userRecord.walletAddress);

    const deployment = await ensureSmartAccount({ ownerAddress });
    await upsertSmartAccountRecord(userId, deployment);

    const updatedRecord = await findSmartAccountByUserId(userId);
    const responseRecord = updatedRecord ?? {
      userId,
      smartAccountAddress: deployment.smartAccountAddress,
      deploymentTxHash: deployment.deploymentTxHash,
      status: deployment.status,
      paymasterPolicyId: deployment.paymasterPolicyId,
      ownerAddress,
      username: userRecord.username,
      alias: userRecord.alias,
    };

    const chainStatus = await getSmartAccountStatus(deployment.smartAccountAddress);

    res.json({
      success: true,
      data: {
        userId,
        ownerAddress,
        smartAccountAddress: deployment.smartAccountAddress,
        requestedStatus: deployment.status,
        onChainStatus: chainStatus.status,
        deploymentTxHash: chainStatus.deploymentTxHash ?? responseRecord.deploymentTxHash ?? null,
        paymasterPolicyId: responseRecord.paymasterPolicyId ?? chainStatus.paymasterPolicyId ?? null,
      },
    });
  } catch (error) {
    next(error);
  }
});

devicesRouter.get('/list', async (req, res, next) => {
  try {
    const userId = Number(req.query.userId ?? 0);
    const data = await listDevices(userId);
    res.json({ success: true, data });
  } catch (error) {
    next(error);
  }
});

devicesRouter.post('/revoke', async (req, res, next) => {
  try {
    const userId = Number(req.body?.userId ?? 0);
    const deviceId = Number(req.body?.deviceId ?? 0);
    if (!userId || !deviceId) {
      throw new ValidationError('userId and deviceId required');
    }
    const data = await revokeDevice(userId, deviceId);
    res.json({ success: true, data });
  } catch (error) {
    next(error);
  }
});
