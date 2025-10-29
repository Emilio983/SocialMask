import rateLimit from 'express-rate-limit';

// Rate limiting
const createRateLimiter = (windowMs, max) => rateLimit({
  windowMs,
  max,
  message: { error: 'Too many requests, please try again later' },
  standardHeaders: true,
  legacyHeaders: false,
});

// General API rate limiter
export const apiLimiter = createRateLimiter(
  parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 60000, // 1 minute
  parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 10,
);

// Strict rate limiter for transaction submission
export const txLimiter = createRateLimiter(
  60000, // 1 minute
  5, // 5 transactions per minute max
);

// Request logging middleware
export const requestLogger = (req, res, next) => {
  const start = Date.now();

  res.on('finish', () => {
    const duration = Date.now() - start;
    console.log(JSON.stringify({
      method: req.method,
      path: req.path,
      status: res.statusCode,
      duration: `${duration}ms`,
      timestamp: new Date().toISOString(),
      // Never log request body (could contain sensitive data)
    }));
  });

  next();
};

// Setup all middleware
export const setupMiddleware = (app) => {
  app.use(requestLogger);
  app.use('/api', apiLimiter);
};

export default {
  setupMiddleware,
  apiLimiter,
  txLimiter,
  requestLogger,
};
