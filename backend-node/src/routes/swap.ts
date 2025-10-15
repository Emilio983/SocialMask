import { Router } from 'express';
import { executeSwapWithFallback, getZeroXQuote } from '../services/swap.js';

export const swapRouter = Router();

swapRouter.get('/quote', async (req, res, next) => {
  try {
    const result = await getZeroXQuote({
      sellAmount: req.query.sellAmount,
      taker: req.query.taker,
    });
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});

swapRouter.post('/execute', async (req, res, next) => {
  try {
    const result = await executeSwapWithFallback(req.body ?? {});
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});
