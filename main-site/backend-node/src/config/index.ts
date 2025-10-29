import { config as loadEnv } from 'dotenv';
import { z } from 'zod';

// Cargar .env desde el directorio del proyecto backend-node
loadEnv();

const baseSchema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('production'),
  PORT: z.coerce.number().int().positive().default(3088),

  // ============================================
  // MySQL Database (REQUIRED)
  // Support both DB_* and MYSQL_* prefixes for compatibility
  // ============================================
  DB_HOST: z.string().min(1, 'DB_HOST or MYSQL_HOST is required').optional(),
  MYSQL_HOST: z.string().min(1).optional(),
  DB_PORT: z.coerce.number().int().positive().default(3306).optional(),
  MYSQL_PORT: z.coerce.number().int().positive().default(3306).optional(),
  DB_USER: z.string().min(1, 'DB_USER or MYSQL_USER is required').optional(),
  MYSQL_USER: z.string().min(1).optional(),
  DB_PASS: z.string().min(1, 'DB_PASS or MYSQL_PASS is required').optional(),
  MYSQL_PASS: z.string().min(1).optional(),
  DB_NAME: z.string().min(1, 'DB_NAME or MYSQL_DB is required').optional(),
  MYSQL_DB: z.string().min(1).optional(),

  // ============================================
  // Pinata IPFS (REQUIRED for file storage)
  // ============================================
  PINATA_API_KEY: z.string().min(20, 'PINATA_API_KEY is required (minimum 20 chars)'),
  PINATA_SECRET_API_KEY: z.string().min(40, 'PINATA_SECRET_API_KEY is required (minimum 40 chars)'),

  // ============================================
  // Pin Proxy Configuration
  // ============================================
  PIN_PROXY_PORT: z.coerce.number().int().min(1024).max(65535).default(3089),
  FILE_MAX_MB: z.coerce.number().int().positive().default(50),
  RATE_LIMIT_MIN: z.coerce.number().int().positive().default(10),
  ALLOWED_ORIGINS: z.string().min(1, 'ALLOWED_ORIGINS is required (comma-separated URLs)'),

  NETWORK: z.string().min(1),
  CHAIN_ID: z.coerce.number().int().positive(),
  POLYGON_RPC_HTTP_URL: z.string().url(),
  POLYGON_RPC_WSS_URL: z.string().url(),

  RP_ID: z.string().min(1).default('localhost'),
  PASSKEY_DERIVATION_SECRET: z.string().min(32),
  ROTATING_ADDRESS_SECRET: z.string().min(32),
  SIMPLE_ACCOUNT_FACTORY_ADDRESS: z.string().regex(/^0x[a-fA-F0-9]{40}$/),
  ENTRY_POINT_ADDRESS: z.string().regex(/^0x[a-fA-F0-9]{40}$/),

  WEB3AUTH_CLIENT_ID: z.string().min(1),
  WEB3AUTH_CLIENT_SECRET: z.string().min(1),
  WEB3AUTH_JWKS_ENDPOINT: z.string().url(),
  WEB3AUTH_ENVIRONMENT: z.string().min(1),

  GELATO_RELAY_API_KEY: z.string().min(1),
  ERC4337_BUNDLER_RPC_URL: z.string().url(),
  PAYMASTER_RPC_URL: z.string().url(),
  PAYMASTER_POLICY_ID: z.string().optional(),
  GAS_SPONSOR_DAILY_USD_LIMIT: z.coerce.number().positive(),
  TXS_PER_DAY_LIMIT: z.coerce.number().int().positive(),

  SWAP_AGGREGATOR: z.enum(['0X']),
  SWAP_API_URL: z.string().url(),
  ZEROX_API_KEY: z.string().min(1),
  SWAP_SLIPPAGE_BPS: z.coerce.number().int().min(1),
  FALLBACK_PREFERRED: z.enum(['QUICKSWAP', 'UNISWAP']),
  QUICKSWAP_ROUTER_ADDRESS: z.string().regex(/^0x[a-fA-F0-9]{40}$/),
  UNISWAP_V3_QUOTER: z.string().regex(/^0x[a-fA-F0-9]{40}$/),
  UNISWAP_V3_POOL_SPHE_USDT: z.string().regex(/^0x[a-fA-F0-9]{64}$/),

  MY_TOKEN_ADDRESS: z.string().regex(/^0x[a-fA-F0-9]{40}$/),
  MY_TOKEN_SYMBOL: z.string().min(1),
  MY_TOKEN_DECIMALS: z.coerce.number().int().min(0),
  USDT_ADDRESS: z.string().regex(/^0x[a-fA-F0-9]{40}$/),
  USDT_DECIMALS: z.coerce.number().int().min(0),
  SPHE_CONTRACT_ADDRESS: z.string().regex(/^0x[a-fA-F0-9]{40}$/),

  SESSION_SECRET: z.string().min(32),
  FX_USD_MXN_RATE: z.coerce.number().positive().default(17),
});

const parsed = baseSchema.safeParse(process.env);

if (!parsed.success) {
  const issues = parsed.error.issues.map((issue) => `${issue.path.join('.')}: ${issue.message}`);
  
  console.error('╔════════════════════════════════════════════════════════════════╗');
  console.error('║ ❌ BACKEND NODE STARTUP FAILED - MISSING ENVIRONMENT VARIABLES ║');
  console.error('╚════════════════════════════════════════════════════════════════╝');
  console.error('');
  console.error('The following environment variables are missing or invalid:');
  console.error('');
  issues.forEach((issue) => {
    console.error(`  🔴 ${issue}`);
  });
  console.error('');
  console.error('Required variables for Pinata:');
  console.error('  - PINATA_API_KEY (get from https://app.pinata.cloud/)');
  console.error('  - PINATA_SECRET_API_KEY');
  console.error('');
  console.error('Required variables for Pin Proxy:');
  console.error('  - PIN_PROXY_PORT (default: 3089)');
  console.error('  - FILE_MAX_MB (default: 50)');
  console.error('  - RATE_LIMIT_MIN (default: 10)');
  console.error('  - ALLOWED_ORIGINS (comma-separated, e.g., https://socialmask.org)');
  console.error('');
  console.error('Required variables for MySQL:');
  console.error('  - DB_HOST or MYSQL_HOST');
  console.error('  - DB_USER or MYSQL_USER');
  console.error('  - DB_PASS or MYSQL_PASS');
  console.error('  - DB_NAME or MYSQL_DB');
  console.error('');
  console.error('Please update /var/www/html/backend-node/.env with the missing values.');
  console.error('');
  
  throw new Error(`❌ Backend startup aborted due to missing environment variables`);
}

type AppConfig = z.infer<typeof baseSchema>;
const cfg: AppConfig = parsed.data;

// Resolve database config with fallbacks
const dbHost = cfg.MYSQL_HOST || cfg.DB_HOST;
const dbPort = cfg.MYSQL_PORT || cfg.DB_PORT || 3306;
const dbUser = cfg.MYSQL_USER || cfg.DB_USER;
const dbPass = cfg.MYSQL_PASS || cfg.DB_PASS;
const dbName = cfg.MYSQL_DB || cfg.DB_NAME;

// Validate that we have all required database config
if (!dbHost || !dbUser || !dbPass || !dbName) {
  console.error('❌ Missing required database configuration');
  console.error('Required: DB_HOST, DB_USER, DB_PASS, DB_NAME');
  console.error('(or MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB)');
  throw new Error('Missing database configuration');
}

export const config = {
  nodeEnv: cfg.NODE_ENV,
  port: cfg.PORT,
  database: {
    host: dbHost,
    port: dbPort,
    user: dbUser,
    password: dbPass,
    name: dbName,
  },
  pinata: {
    apiKey: cfg.PINATA_API_KEY,
    secretApiKey: cfg.PINATA_SECRET_API_KEY,
  },
  pinProxy: {
    port: cfg.PIN_PROXY_PORT,
    fileMaxMb: cfg.FILE_MAX_MB,
    rateLimitMin: cfg.RATE_LIMIT_MIN,
    allowedOrigins: cfg.ALLOWED_ORIGINS.split(',').map(o => o.trim()),
  },
  network: cfg.NETWORK,
  chainId: cfg.CHAIN_ID,
  polygonRpc: {
    http: cfg.POLYGON_RPC_HTTP_URL,
    wss: cfg.POLYGON_RPC_WSS_URL,
  },
  passkeys: {
    rpId: cfg.RP_ID,
    derivationSecret: cfg.PASSKEY_DERIVATION_SECRET,
  },
  rotatingAddresses: {
    derivationSecret: cfg.ROTATING_ADDRESS_SECRET,
  },
  smartAccounts: {
    factory: cfg.SIMPLE_ACCOUNT_FACTORY_ADDRESS,
    entryPoint: cfg.ENTRY_POINT_ADDRESS,
    defaultSalt: 0n,
  },
  web3Auth: {
    clientId: cfg.WEB3AUTH_CLIENT_ID,
    clientSecret: cfg.WEB3AUTH_CLIENT_SECRET,
    jwksEndpoint: cfg.WEB3AUTH_JWKS_ENDPOINT,
    environment: cfg.WEB3AUTH_ENVIRONMENT,
  },
  gelato: {
    apiKey: cfg.GELATO_RELAY_API_KEY,
    bundlerUrl: cfg.ERC4337_BUNDLER_RPC_URL,
    paymasterUrl: cfg.PAYMASTER_RPC_URL,
    policyId: cfg.PAYMASTER_POLICY_ID ?? '',
    gasSponsorLimitUsd: cfg.GAS_SPONSOR_DAILY_USD_LIMIT,
    txsPerDayLimit: cfg.TXS_PER_DAY_LIMIT,
  },
  swaps: {
    aggregator: cfg.SWAP_AGGREGATOR,
    apiUrl: cfg.SWAP_API_URL,
    zeroXApiKey: cfg.ZEROX_API_KEY,
    slippageBps: cfg.SWAP_SLIPPAGE_BPS,
    fallbackPreferred: cfg.FALLBACK_PREFERRED,
    quickswapRouter: cfg.QUICKSWAP_ROUTER_ADDRESS,
    uniswapV3Quoter: cfg.UNISWAP_V3_QUOTER,
    uniswapPoolSpheUsdt: cfg.UNISWAP_V3_POOL_SPHE_USDT,
  },
  tokens: {
    sphe: {
      address: cfg.MY_TOKEN_ADDRESS,
      symbol: cfg.MY_TOKEN_SYMBOL,
      decimals: cfg.MY_TOKEN_DECIMALS,
    },
    usdt: {
      address: cfg.USDT_ADDRESS,
      decimals: cfg.USDT_DECIMALS,
    },
  },
  sessionSecret: cfg.SESSION_SECRET,
  fxRateUsdMxn: cfg.FX_USD_MXN_RATE,
} as const;

export type Config = typeof config;
