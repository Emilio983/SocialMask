import express from 'express';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import http from 'http';
import { config } from './config/index.js';
import { httpLogger, logger } from './utils/logger.js';
import { AppError } from './utils/errors.js';
import { authRouter } from './routes/auth.js';
import { devicesRouter } from './routes/devices.js';
import { receiveRouter } from './routes/receive.js';
import { swapRouter } from './routes/swap.js';
import { withdrawRouter } from './routes/withdraw.js';
import { walletRouter } from './routes/wallet.js';
import { limitsRouter } from './routes/limits.js';
import actionsRouter from './routes/actions.js';
import p2pRouter from './routes/p2p.routes.js';
import { startDepositWatcher } from './workers/depositWatcher.js';
import { startSwapMonitor } from './workers/swapMonitor.js';
import { startWithdrawMonitor } from './workers/withdrawMonitor.js';
import { startRotatingSweepMonitor } from './workers/rotatingSweepMonitor.js';
import { startActionsMonitor } from './workers/actionsMonitor.js';
import { WebSocketSignalingService } from './services/websocket-signaling.service.js';

const app = express();
const server = http.createServer(app);

app.disable('x-powered-by');
app.use(helmet());
app.use(express.json());
app.use(express.urlencoded({ extended: false }));
app.use(httpLogger);

const limiter = rateLimit({
  windowMs: 60 * 1000,
  max: 120,
});
app.use(limiter);

app.get('/health', (_req, res) => {
  res.json({ status: 'ok', chainId: config.chainId });
});

app.use('/auth', authRouter);
app.use('/devices', devicesRouter);
app.use('/receive', receiveRouter);
app.use('/swap', swapRouter);
app.use('/wallet', walletRouter);
app.use('/withdraw', withdrawRouter);
app.use('/limits', limitsRouter);
app.use('/actions', actionsRouter);
app.use('/p2p', p2pRouter);

app.use((req, _res, next) => {
  next(new AppError(404, 'not_found', `Route ${req.method} ${req.originalUrl} not found`));
});

app.use((err: unknown, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
  if (err instanceof AppError) {
    res.status(err.status).json({ success: false, code: err.code, message: err.message, details: err.details });
    return;
  }

  logger.error({ err }, 'Unhandled error');
  res.status(500).json({ success: false, code: 'internal_error', message: 'Internal server error' });
});

server.listen(config.port, () => {
  logger.info(`Sphoria Node backend listening on port ${config.port}`);
  
  // Initialize WebSocket signaling service
  const wsService = new WebSocketSignalingService(server);
  logger.info('WebSocket signaling service initialized');
  
  // Start monitors only if enabled
  const enableMonitors = process.env.ENABLE_MONITORS !== 'false';
  if (enableMonitors) {
    try {
      startDepositWatcher();
      startSwapMonitor();
      startWithdrawMonitor();
      startRotatingSweepMonitor();
      startActionsMonitor();
    } catch (error) {
      logger.warn({ error }, 'Some monitors failed to start, continuing without them');
    }
  } else {
    logger.info('Blockchain monitors disabled');
  }
});

process.on('SIGINT', () => {
  logger.info('Shutting down gracefully');
  server.close(() => process.exit(0));
});

process.on('SIGTERM', () => {
  logger.info('SIGTERM received');
  server.close(() => process.exit(0));
});
