import pino from 'pino';
import pinoHttpModule from 'pino-http';
import { config } from '../config/index.js';

const pinoHttp = pinoHttpModule.default || pinoHttpModule;

const isDev = config.nodeEnv !== 'production';

export const logger = pino({
  level: isDev ? 'debug' : 'info',
  transport: isDev
    ? {
        target: 'pino-pretty',
        options: {
          translateTime: 'SYS:standard',
          colorize: true,
          ignore: 'pid,hostname',
        },
      }
    : undefined,
  redact: {
    paths: ['req.headers.authorization', 'req.headers.cookie'],
    censor: '[REDACTED]',
  },
});

export const httpLogger = pinoHttp({
  logger,
  autoLogging: {
    ignore: (req: any) => req.url === '/health',
  },
  customLogLevel: (res: any, err: any) => {
    if (err || res.statusCode >= 500) return 'error';
    if (res.statusCode >= 400) return 'warn';
    return 'info';
  },
});
