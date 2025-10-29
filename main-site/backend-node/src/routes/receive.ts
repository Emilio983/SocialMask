import { Router } from 'express';
import { generateRotatedAddress } from '../services/addressRotation.js';

export const receiveRouter = Router();

receiveRouter.post('/address', async (req, res, next) => {
  try {
    const { smartAccountAddress, forceNew } = req.body ?? {};
    const result = await generateRotatedAddress(smartAccountAddress, { forceNew: Boolean(forceNew) });
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});
