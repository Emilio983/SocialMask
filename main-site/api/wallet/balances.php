<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../../config/constants.php';

requireAuth();

/**
 * Consulta el balance de un token ERC-20 en Polygon
 */
function getERC20Balance(string $tokenAddress, string $walletAddress): array {
    $rpcUrl = defined('POLYGON_RPC_HTTP_URL') ? POLYGON_RPC_HTTP_URL : 'https://polygon-rpc.com';
    
    // ABI mínimo para balanceOf
    $data = '0x70a08231' . str_pad(substr($walletAddress, 2), 64, '0', STR_PAD_LEFT);
    
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'eth_call',
        'params' => [
            [
                'to' => $tokenAddress,
                'data' => $data
            ],
            'latest'
        ]
    ]);
    
    $ch = curl_init($rpcUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 2
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("RPC error: $error");
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['result'])) {
        throw new Exception('Invalid RPC response');
    }
    
    $hexBalance = $result['result'];
    $rawBalance = hexdec($hexBalance);
    
    return ['raw' => (string)$rawBalance, 'hex' => $hexBalance];
}

/**
 * Formatea el balance de wei a unidades legibles
 */
function formatBalance(string $rawBalance, int $decimals = 18): string {
    $balance = bcdiv($rawBalance, bcpow('10', (string)$decimals, 0), $decimals);
    
    // Redondear a 2 decimales para mostrar
    $rounded = number_format((float)$balance, 2, '.', '');
    
    return $rounded;
}

try {
    // Obtener smart account address desde la tabla smart_accounts
    $stmt = $pdo->prepare('
        SELECT sa.smart_account_address, sa.is_deployed
        FROM smart_accounts sa
        WHERE sa.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || empty($row['smart_account_address'])) {
        // Si no tiene smart account, retornar balances en 0
        echo json_encode([
            'success' => true,
            'smart_account_address' => null,
            'balances' => [
                'sphe' => [
                    'raw' => '0',
                    'formatted' => '0.00'
                ],
                'usdt' => [
                    'raw' => '0',
                    'formatted' => '0.00'
                ]
            ],
            'note' => 'Smart Account pendiente de creación'
        ]);
        exit;
    }

    $address = $row['smart_account_address'];
    $isDeployed = (bool)($row['is_deployed'] ?? false);

    // Direcciones de tokens en Polygon
    $usdtAddress = defined('USDT_ADDRESS') ? USDT_ADDRESS : '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';
    $spheAddress = defined('SPHE_CONTRACT_ADDRESS') ? SPHE_CONTRACT_ADDRESS : null;
    
    $balances = [];
    
    // Obtener balance de USDT
    try {
        $usdtData = getERC20Balance($usdtAddress, $address);
        $balances['usdt'] = [
            'raw' => $usdtData['raw'],
            'formatted' => formatBalance($usdtData['raw'], 6) // USDT tiene 6 decimales
        ];
    } catch (Exception $e) {
        error_log('Error fetching USDT balance: ' . $e->getMessage());
        $balances['usdt'] = [
            'raw' => '0',
            'formatted' => '0.00'
        ];
    }
    
    // Obtener balance de SPHE si está definido
    if ($spheAddress) {
        try {
            $spheData = getERC20Balance($spheAddress, $address);
            $balances['sphe'] = [
                'raw' => $spheData['raw'],
                'formatted' => formatBalance($spheData['raw'], 18)
            ];
        } catch (Exception $e) {
            error_log('Error fetching SPHE balance: ' . $e->getMessage());
            $balances['sphe'] = [
                'raw' => '0',
                'formatted' => '0.00'
            ];
        }
    } else {
        $balances['sphe'] = [
            'raw' => '0',
            'formatted' => '0.00'
        ];
    }

    echo json_encode([
        'success' => true,
        'smart_account_address' => $address,
        'is_deployed' => $isDeployed,
        'balances' => $balances,
        'source' => 'polygon_rpc',
        'timestamp' => time()
    ]);
    
} catch (Throwable $e) {
    error_log('wallet/balances error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudieron obtener los balances',
    ]);
}
