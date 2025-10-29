import { Router } from 'express';
import { startPasskeyLogin, finishPasskeyLogin, startPasskeyRegistration, finishPasskeyRegistration } from '../services/auth.js';

export const authRouter = Router();

// Login endpoints
authRouter.post('/passkey/start', async (req, res, next) => {
  try {
    const result = await startPasskeyLogin(req.body);
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});

authRouter.post('/passkey/finish', async (req, res, next) => {
  try {
    const result = await finishPasskeyLogin(req.body);
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});

// Registration endpoints
authRouter.post('/passkey/register/start', async (req, res, next) => {
  try {
    const result = await startPasskeyRegistration(req.body);
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});

authRouter.post('/passkey/register/finish', async (req, res, next) => {
  try {
    const result = await finishPasskeyRegistration(req.body);
    res.json({ success: true, data: result });
  } catch (error) {
    next(error);
  }
});
