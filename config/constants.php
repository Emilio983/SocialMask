<?php
declare(strict_types=1);

use TheSocialMask\Config\Env;

require_once __DIR__ . '/env.php';

Env::load();

$requiredKeys = [
    'NETWORK',
    'CHAIN_ID',
    'POLYGON_RPC_HTTP_URL',
    'POLYGON_RPC_WSS_URL',
    'WEB3AUTH_CLIENT_ID',
    'WEB3AUTH_CLIENT_SECRET',
    'WEB3AUTH_JWKS_ENDPOINT',
    'WEB3AUTH_ENVIRONMENT',
    'GELATO_RELAY_API_KEY',
    'ERC4337_BUNDLER_RPC_URL',
    'PAYMASTER_RPC_URL',
    'GAS_SPONSOR_DAILY_USD_LIMIT',
    'TXS_PER_DAY_LIMIT',
    'SWAP_AGGREGATOR',
    'SWAP_API_URL',
    'ZEROX_API_KEY',
    'SWAP_SLIPPAGE_BPS',
    'FALLBACK_PREFERRED',
    'QUICKSWAP_ROUTER_ADDRESS',
    'UNISWAP_V3_QUOTER',
    'UNISWAP_V3_POOL_SPHE_USDT',
    'MY_TOKEN_ADDRESS',
    'MY_TOKEN_SYMBOL',
    'MY_TOKEN_DECIMALS',
    'USDT_ADDRESS',
    'USDT_DECIMALS',
    'SPHE_CONTRACT_ADDRESS',
    'NODE_BACKEND_BASE_URL',
    'FX_USD_MXN_RATE',
];

Env::requireMany($requiredKeys);

if (!defined('NETWORK')) {
    define('NETWORK', Env::require('NETWORK'));
}

if (!defined('CHAIN_ID')) {
    define('CHAIN_ID', Env::int('CHAIN_ID', 0));
}

if (!defined('POLYGON_RPC_HTTP_URL')) {
    define('POLYGON_RPC_HTTP_URL', Env::require('POLYGON_RPC_HTTP_URL'));
}

if (!defined('POLYGON_RPC_WSS_URL')) {
    define('POLYGON_RPC_WSS_URL', Env::require('POLYGON_RPC_WSS_URL'));
}

if (!defined('POLYGON_AMOY_RPC_HTTP_URL')) {
    define('POLYGON_AMOY_RPC_HTTP_URL', Env::get('POLYGON_AMOY_RPC_HTTP_URL'));
}

if (!defined('WEB3AUTH_CLIENT_ID')) {
    define('WEB3AUTH_CLIENT_ID', Env::require('WEB3AUTH_CLIENT_ID'));
}

if (!defined('WEB3AUTH_CLIENT_SECRET')) {
    define('WEB3AUTH_CLIENT_SECRET', Env::require('WEB3AUTH_CLIENT_SECRET'));
}

if (!defined('WEB3AUTH_JWKS_ENDPOINT')) {
    define('WEB3AUTH_JWKS_ENDPOINT', Env::require('WEB3AUTH_JWKS_ENDPOINT'));
}

if (!defined('WEB3AUTH_ENVIRONMENT')) {
    define('WEB3AUTH_ENVIRONMENT', Env::require('WEB3AUTH_ENVIRONMENT'));
}

if (!defined('GELATO_RELAY_API_KEY')) {
    define('GELATO_RELAY_API_KEY', Env::require('GELATO_RELAY_API_KEY'));
}

if (!defined('ERC4337_BUNDLER_RPC_URL')) {
    define('ERC4337_BUNDLER_RPC_URL', Env::require('ERC4337_BUNDLER_RPC_URL'));
}

if (!defined('PAYMASTER_RPC_URL')) {
    define('PAYMASTER_RPC_URL', Env::require('PAYMASTER_RPC_URL'));
}

if (!defined('PAYMASTER_POLICY_ID')) {
    define('PAYMASTER_POLICY_ID', Env::get('PAYMASTER_POLICY_ID', ''));
}

if (!defined('GAS_SPONSOR_DAILY_USD_LIMIT')) {
    define('GAS_SPONSOR_DAILY_USD_LIMIT', Env::float('GAS_SPONSOR_DAILY_USD_LIMIT', 0.0));
}

if (!defined('TXS_PER_DAY_LIMIT')) {
    define('TXS_PER_DAY_LIMIT', Env::int('TXS_PER_DAY_LIMIT', 0));
}

if (!defined('SWAP_AGGREGATOR')) {
    define('SWAP_AGGREGATOR', strtoupper(Env::require('SWAP_AGGREGATOR')));
}

if (!defined('SWAP_API_URL')) {
    define('SWAP_API_URL', Env::require('SWAP_API_URL'));
}

if (!defined('ZEROX_API_KEY')) {
    define('ZEROX_API_KEY', Env::require('ZEROX_API_KEY'));
}

if (!defined('SWAP_SLIPPAGE_BPS')) {
    define('SWAP_SLIPPAGE_BPS', Env::int('SWAP_SLIPPAGE_BPS', 50));
}

if (!defined('FALLBACK_PREFERRED')) {
    define('FALLBACK_PREFERRED', strtoupper(Env::require('FALLBACK_PREFERRED')));
}

if (!defined('QUICKSWAP_ROUTER_ADDRESS')) {
    define('QUICKSWAP_ROUTER_ADDRESS', Env::require('QUICKSWAP_ROUTER_ADDRESS'));
}

if (!defined('UNISWAP_V3_QUOTER')) {
    define('UNISWAP_V3_QUOTER', Env::require('UNISWAP_V3_QUOTER'));
}

if (!defined('UNISWAP_V3_POOL_SPHE_USDT')) {
    define('UNISWAP_V3_POOL_SPHE_USDT', Env::require('UNISWAP_V3_POOL_SPHE_USDT'));
}

if (!defined('MY_TOKEN_ADDRESS')) {
    define('MY_TOKEN_ADDRESS', Env::require('MY_TOKEN_ADDRESS'));
}

if (!defined('MY_TOKEN_SYMBOL')) {
    define('MY_TOKEN_SYMBOL', Env::require('MY_TOKEN_SYMBOL'));
}

if (!defined('MY_TOKEN_DECIMALS')) {
    define('MY_TOKEN_DECIMALS', Env::int('MY_TOKEN_DECIMALS', 18));
}

if (!defined('USDT_ADDRESS')) {
    define('USDT_ADDRESS', Env::require('USDT_ADDRESS'));
}

if (!defined('USDT_DECIMALS')) {
    define('USDT_DECIMALS', Env::int('USDT_DECIMALS', 6));
}

if (!defined('SPHE_CONTRACT_ADDRESS')) {
    define('SPHE_CONTRACT_ADDRESS', Env::require('SPHE_CONTRACT_ADDRESS'));
}

if (!defined('NODE_BACKEND_BASE_URL')) {
    define('NODE_BACKEND_BASE_URL', Env::require('NODE_BACKEND_BASE_URL'));
}

if (!defined('FX_USD_MXN_RATE')) {
    define('FX_USD_MXN_RATE', Env::float('FX_USD_MXN_RATE', 17.0));
}
