<?php
/**
 * API: Obtener cotización de swap usando 0x API
 * GET /api/wallet/swap_quote.php
 */

declare(strict_types=1);

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
require_once __DIR__ . '/../../utils/node_client.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $fromToken = isset($_GET['fromToken']) ? strtoupper(trim($_GET['fromToken'])) : '';
    $toToken = isset($_GET['toToken']) ? strtoupper(trim($_GET['toToken'])) : '';
    $amount = isset($_GET['amount']) ? trim($_GET['amount']) : '';
    $slippage = isset($_GET['slippage']) ? floatval($_GET['slippage']) : 1.0;

    // Validaciones
    if (empty($fromToken) || empty($toToken) || empty($amount)) {
        throw new InvalidArgumentException('Faltan parámetros requeridos');
    }

    if (!in_array($fromToken, ['USDT', 'SPHE'], true) || !in_array($toToken, ['USDT', 'SPHE'], true)) {
        throw new InvalidArgumentException('Tokens inválidos');
    }

    if ($fromToken === $toToken) {
        throw new InvalidArgumentException('No puedes intercambiar el mismo token');
    }

    $amountFloat = floatval($amount);
    if ($amountFloat <= 0) {
        throw new InvalidArgumentException('Cantidad inválida');
    }

    // Obtener smart account del usuario
    $stmt = $pdo->prepare('
        SELECT sa.smart_account_address, sa.is_deployed
        FROM smart_accounts sa
        WHERE sa.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || empty($account['smart_account_address'])) {
        throw new RuntimeException('Smart account no encontrada');
    }

    if (!$account['is_deployed']) {
        throw new RuntimeException('Smart account debe estar desplegada. Primero recibe fondos.');
    }

    $smartAccount = $account['smart_account_address'];

    // Llamar al backend Node.js para obtener cotización de 0x
    $quoteParams = [
        'smartAccountAddress' => $smartAccount,
        'fromToken' => $fromToken,
        'toToken' => $toToken,
        'sellAmount' => $amount,
        'slippagePercentage' => $slippage
    ];

    $queryString = http_build_query($quoteParams);
    $nodeResp = nodeApiRequest('GET', 'wallet/swap-quote?' . $queryString);
    $quoteData = $nodeResp['data'] ?? $nodeResp;

    if (!isset($quoteData['buyAmount'])) {
        throw new RuntimeException('Respuesta de cotización inválida');
    }

    // Calcular información adicional
    $buyAmount = $fromToken === 'USDT' ? 
        floatval($quoteData['buyAmount']) / 1e18 : // SPHE has 18 decimals
        floatval($quoteData['buyAmount']) / 1e6;   // USDT has 6 decimals
    
    $sellAmount = $fromToken === 'USDT' ? 
        floatval($amount) : // Already in USDT
        floatval($amount);   // Already in SPHE

    $price = $buyAmount / $sellAmount;
    $guaranteedAmount = $buyAmount * (1 - ($slippage / 100));

    echo json_encode([
        'success' => true,
        'quote' => [
            'sellAmount' => $amount,
            'buyAmount' => number_format($buyAmount, 6, '.', ''),
            'guaranteedAmount' => number_format($guaranteedAmount, 6, '.', ''),
            'price' => number_format($price, 6, '.', ''),
            'slippage' => $slippage,
            'priceImpact' => $quoteData['estimatedPriceImpact'] ?? null,
            'gas' => $quoteData['gas'] ?? null,
            'rawQuote' => $quoteData
        ],
        'smartAccount' => $smartAccount
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('swap_quote.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener cotización'
    ]);
}
