<?php
/**
 * BLOCKCHAIN CONFIGURATION
 * Configuración para interactuar con Polygon y el contrato SPHE
 */

// Token SPHE en Polygon
define('SPHE_TOKEN_ADDRESS', '0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b');
define('SPHE_DECIMALS', 18); // ERC-20 standard decimals

// Contrato Escrow (se actualizará después del deploy)
define('ESCROW_CONTRACT_ADDRESS', ''); // TODO: Actualizar después del deploy

// Infura Configuration
define('INFURA_API_KEY', 'f210fc05834a4070871dbc89b2774608');
define('INFURA_PROJECT_SECRET', 'XQgMhull9p0+TbfYvgnylRQ0oIBXsJdz0+qbtycqdGpuhbO6Padiug');
define('INFURA_GAS_API', 'https://gas.api.infura.io/v3/' . INFURA_API_KEY);

// Treasury Wallet (receives all SPHE payments) - Cargar desde .env en producción
define('TREASURY_WALLET', getenv('TREASURY_WALLET') ?: '0x0000000000000000000000000000000000000000');

// Polygon Network Configuration
define('POLYGON_MAINNET_RPC', 'https://polygon-mainnet.infura.io/v3/' . INFURA_API_KEY);
define('POLYGON_TESTNET_RPC', 'https://polygon-amoy.infura.io/v3/' . INFURA_API_KEY); // Amoy testnet
define('POLYGON_CHAIN_ID', 137); // Mainnet
define('POLYGON_TESTNET_CHAIN_ID', 80002); // Amoy testnet

// Configuración de red actual
define('USE_TESTNET', false); // ✅ PRODUCCIÓN - Usando Polygon Mainnet
define('CURRENT_RPC', USE_TESTNET ? POLYGON_TESTNET_RPC : POLYGON_MAINNET_RPC);
define('CURRENT_CHAIN_ID', USE_TESTNET ? POLYGON_TESTNET_CHAIN_ID : POLYGON_CHAIN_ID);

// Explorers
define('POLYGONSCAN_URL', USE_TESTNET ? 'https://amoy.polygonscan.com' : 'https://polygonscan.com');
define('POLYGONSCAN_API_KEY', ''); // Opcional para rate limiting

// Configuración de confirmaciones
define('MIN_CONFIRMATIONS', 3); // Mínimo de confirmaciones para considerar válida una tx
define('CONFIRMATION_TIMEOUT', 300); // Segundos máximos para esperar confirmaciones

// Gas settings
define('GAS_LIMIT_DEPOSIT', 100000); // Gas limit para deposit
define('GAS_LIMIT_FINALIZE', 500000); // Gas limit para finalizeSurvey
define('GAS_LIMIT_PAYOUT_BATCH', 300000); // Gas limit para payoutBatch

// Configuración de polling para cron job
define('PAYMENT_CHECK_INTERVAL', 60); // Segundos entre cada check de pagos pendientes
define('MAX_PAYMENTS_PER_BATCH', 50); // Máximo de pagos a verificar por batch

// Configuración de retry
define('MAX_RETRIES', 3); // Reintentos máximos para transacciones fallidas
define('RETRY_DELAY', 5); // Segundos entre reintentos

// ABIs de contratos (se cargarán desde archivos JSON)
define('ABI_DIR', __DIR__ . '/../abis/');
define('SPHE_ABI_PATH', ABI_DIR . 'SPHE.json');
define('ESCROW_ABI_PATH', ABI_DIR . 'SurveyEscrow.json');

/**
 * Obtener configuración de red actual
 * @return array
 */
function getNetworkConfig() {
    return [
        'rpc_url' => CURRENT_RPC,
        'chain_id' => CURRENT_CHAIN_ID,
        'is_testnet' => USE_TESTNET,
        'explorer_url' => POLYGONSCAN_URL,
        'sphe_token' => SPHE_TOKEN_ADDRESS,
        'escrow_contract' => ESCROW_CONTRACT_ADDRESS,
    ];
}

/**
 * Cargar ABI desde archivo JSON
 * @param string $contract_name 'SPHE' o 'SurveyEscrow'
 * @return array|false
 */
function loadABI($contract_name) {
    $file_path = ABI_DIR . $contract_name . '.json';

    if (!file_exists($file_path)) {
        error_log("ABI file not found: {$file_path}");
        return false;
    }

    $json = file_get_contents($file_path);
    $data = json_decode($json, true);

    // El archivo puede contener { "abi": [...] } o directamente [...]
    if (isset($data['abi'])) {
        return $data['abi'];
    }

    return $data;
}

/**
 * Convertir SPHE a Wei (cantidad más pequeña)
 * @param float $sphe_amount
 * @return string
 */
function spheToWei($sphe_amount) {
    return bcmul((string)$sphe_amount, bcpow('10', (string)SPHE_DECIMALS));
}

/**
 * Convertir Wei a SPHE
 * @param string $wei_amount
 * @return string
 */
function weiToSphe($wei_amount) {
    return bcdiv($wei_amount, bcpow('10', (string)SPHE_DECIMALS), SPHE_DECIMALS);
}

/**
 * Obtener URL del explorador para una transacción
 * @param string $tx_hash
 * @return string
 */
function getExplorerTxUrl($tx_hash) {
    return POLYGONSCAN_URL . '/tx/' . $tx_hash;
}

/**
 * Obtener URL del explorador para una dirección
 * @param string $address
 * @return string
 */
function getExplorerAddressUrl($address) {
    return POLYGONSCAN_URL . '/address/' . $address;
}

/**
 * Validar dirección de Ethereum/Polygon
 * @param string $address
 * @return bool
 */
function isValidAddress($address) {
    return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}

/**
 * Validar hash de transacción
 * @param string $tx_hash
 * @return bool
 */
function isValidTxHash($tx_hash) {
    return preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash);
}
