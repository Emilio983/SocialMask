import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import dotenv from 'dotenv';
import crypto from 'crypto';
import {
  initGrpcClient,
  getLatestBlock,
  getBlock,
  sendTransaction,
  getTransaction,
  getLightdInfo,
  getAddressBalance
} from './grpc-client.js';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(helmet({
  contentSecurityPolicy: false,
  crossOriginEmbedderPolicy: false
}));

app.use(cors({
  origin: process.env.CORS_ORIGIN || '*',
  methods: ['GET', 'POST', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization']
}));

app.use(express.json({ limit: '10mb' }));

// Rate limiting
const limiter = rateLimit({
  windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 60000,
  max: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 100,
  message: { error: 'Too many requests, please try again later' }
});

app.use(limiter);

// Request logging
app.use((req, res, next) => {
  const start = Date.now();
  res.on('finish', () => {
    const duration = Date.now() - start;
    console.log(`[${new Date().toISOString()}] ${req.method} ${req.path} ${res.statusCode} ${duration}ms`);
  });
  next();
});

// Health check
app.get('/health', (req, res) => {
  res.json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    service: 'zcash-grpc-rest-bridge',
    version: '1.0.0'
  });
});

// Get latest block (equivalent to /blocks/head)
app.get('/blocks/head', async (req, res) => {
  try {
    const block = await getLatestBlock();
    res.json({
      height: block.height,
      hash: block.hash,
      time: Math.floor(Date.now() / 1000)
    });
  } catch (error) {
    console.error('[/blocks/head] Error:', error.message);
    res.status(500).json({
      error: 'Failed to get latest block',
      message: error.message
    });
  }
});

// Get block by height
app.get('/blocks/:height', async (req, res) => {
  try {
    const height = parseInt(req.params.height);
    if (isNaN(height) || height < 0) {
      return res.status(400).json({ error: 'Invalid block height' });
    }
    
    const block = await getBlock(height);
    res.json(block);
  } catch (error) {
    console.error('[/blocks/:height] Error:', error.message);
    res.status(500).json({
      error: 'Failed to get block',
      message: error.message
    });
  }
});

// Submit transaction
app.post('/tx/submit', async (req, res) => {
  try {
    const { rawTxHex } = req.body;
    
    if (!rawTxHex || typeof rawTxHex !== 'string') {
      return res.status(400).json({ error: 'rawTxHex required as string' });
    }
    
    // Validate hex format
    if (!/^[0-9a-fA-F]+$/.test(rawTxHex)) {
      return res.status(400).json({ error: 'Invalid hex format' });
    }
    
    const result = await sendTransaction(rawTxHex);
    
    res.json({
      success: true,
      txid: result.txid,
      status: 'broadcasted'
    });
  } catch (error) {
    console.error('[/tx/submit] Error:', error.message);
    res.status(500).json({
      error: 'Failed to submit transaction',
      message: error.message
    });
  }
});

// Get transaction status
app.get('/tx/:txid', async (req, res) => {
  try {
    const { txid } = req.params;
    
    if (!/^[0-9a-fA-F]{64}$/.test(txid)) {
      return res.status(400).json({ error: 'Invalid txid format' });
    }
    
    const tx = await getTransaction(txid);
    res.json({
      txid,
      height: tx.height,
      confirmations: tx.confirmations,
      data: tx.data
    });
  } catch (error) {
    console.error('[/tx/:txid] Error:', error.message);
    res.status(404).json({
      error: 'Transaction not found',
      message: error.message
    });
  }
});

// Get lightwalletd info
app.get('/info', async (req, res) => {
  try {
    const info = await getLightdInfo();
    res.json(info);
  } catch (error) {
    console.error('[/info] Error:', error.message);
    res.status(500).json({
      error: 'Failed to get lightwalletd info',
      message: error.message
    });
  }
});

// Get address balance (transparent addresses)
app.post('/address/balance', async (req, res) => {
  try {
    const { addresses } = req.body;
    
    if (!Array.isArray(addresses) || addresses.length === 0) {
      return res.status(400).json({ error: 'addresses array required' });
    }
    
    const balance = await getAddressBalance(addresses);
    res.json(balance);
  } catch (error) {
    console.error('[/address/balance] Error:', error.message);
    res.status(500).json({
      error: 'Failed to get address balance',
      message: error.message
    });
  }
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({ error: 'Endpoint not found' });
});

// Error handler
app.use((err, req, res, next) => {
  console.error('[Error]', err);
  res.status(500).json({
    error: 'Internal server error',
    message: err.message
  });
});

// Initialize gRPC client and start server
async function startServer() {
  // Start HTTP server immediately
  app.listen(PORT, '0.0.0.0', () => {
    console.log(`[Bridge] REST→gRPC bridge listening on port ${PORT}`);
    console.log(`[Bridge] Target: ${process.env.LIGHTWALLETD_HOST}:${process.env.LIGHTWALLETD_PORT}`);
    console.log(`[Bridge] Environment: ${process.env.NODE_ENV || 'development'}`);
  });

  // Try to initialize gRPC client (will retry on first request if fails)
  try {
    console.log('[Bridge] Initializing gRPC client...');
    await initGrpcClient();
    console.log('[Bridge] gRPC client initialized successfully');
  } catch (error) {
    console.warn('[Bridge] Initial gRPC connection failed, will retry on requests:', error.message);
  }
}

startServer();
