<?php
/**
 * API: Ejecutar swap de tokens usando 0x
 * POST /api/wallet/swap_execute.php
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../../utils/node_client.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new InvalidArgumentException('Datos JSON inválidos');
    }

    $fromToken = isset($input['fromToken']) ? strtoupper(trim($input['fromToken'])) : '';
    $toToken = isset($input['toToken']) ? strtoupper(trim($input['toToken'])) : '';
    $amount = isset($input['amount']) ? trim($input['amount']) : '';
    $slippage = isset($input['slippage']) ? floatval($input['slippage']) : 1.0;

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
        throw new RuntimeException('Smart account debe estar desplegada');
    }

    $smartAccount = $account['smart_account_address'];

    // SEGURIDAD: Rate limiting - máximo 10 swaps cada 10 minutos
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as swap_count
        FROM transaction_history
        WHERE user_id = ?
        AND type = "swap"
        AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $rateLimitCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rateLimitCheck && $rateLimitCheck['swap_count'] >= 10) {
        throw new RuntimeException('Límite de swaps excedido. Máximo 10 swaps cada 10 minutos.');
    }

    // Verificar balance
    try {
        $balancesResponse = nodeApiRequest('GET', 'wallet/balances?address=' . urlencode($smartAccount));
        $balances = $balancesResponse['data'] ?? $balancesResponse;
        
        $tokenLower = strtolower($fromToken);
        $availableBalance = floatval($balances[$tokenLower]['formatted'] ?? 0);

        if ($amountFloat > $availableBalance) {
            throw new RuntimeException("Saldo insuficiente. Disponible: {$availableBalance} {$fromToken}");
        }
    } catch (Exception $e) {
        error_log('Balance check failed: ' . $e->getMessage());
    }

    // Ejecutar swap mediante Node.js backend
    $swapPayload = [
        'smartAccountAddress' => $smartAccount,
        'userId' => $_SESSION['user_id'],
        'fromToken' => $fromToken,
        'toToken' => $toToken,
        'sellAmount' => $amount,
        'slippagePercentage' => $slippage
    ];

    try {
        $swapResponse = nodeApiRequest('POST', 'wallet/swap-execute', $swapPayload);
        $data = $swapResponse['data'] ?? $swapResponse;

        if (!isset($data['success']) || !$data['success']) {
            throw new RuntimeException($data['message'] ?? 'Error desconocido en swap');
        }

        // Registrar en historial
        try {
            $stmt = $pdo->prepare('
                INSERT INTO transaction_history 
                (user_id, type, token, amount, to_address, tx_hash, status, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $_SESSION['user_id'],
                'swap',
                $fromToken . '→' . $toToken,
                $amount,
                $smartAccount,
                $data['txHash'] ?? null,
                'completed',
                json_encode([
                    'fromToken' => $fromToken,
                    'toToken' => $toToken,
                    'buyAmount' => $data['buyAmount'] ?? null,
                    'slippage' => $slippage
                ])
            ]);
        } catch (Exception $e) {
            error_log('Failed to save swap history: ' . $e->getMessage());
        }

        // Log de auditoría
        try {
            $stmt = $pdo->prepare('
                INSERT INTO security_audit_log 
                (user_id, action, details, ip_address, user_agent, timestamp)
                VALUES (?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $_SESSION['user_id'],
                'wallet_swap',
                json_encode([
                    'fromToken' => $fromToken,
                    'toToken' => $toToken,
                    'amount' => $amount,
                    'smartAccount' => $smartAccount,
                    'tx_hash' => $data['txHash'] ?? null
                ]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log('Failed to save audit log: ' . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => "Swap completado: {$amountFloat} {$fromToken} → {$toToken}",
            'tx_hash' => $data['txHash'] ?? null,
            'buyAmount' => $data['buyAmount'] ?? null,
            'data' => $data
        ]);

    } catch (Exception $e) {
        error_log('Swap execution failed: ' . $e->getMessage());
        throw new RuntimeException('Error al ejecutar swap: ' . $e->getMessage());
    }

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
    error_log('swap_execute.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar el swap'
    ]);
}
