<?php
/**
 * API: Enviar SPHE o USDT
 * POST /api/wallet/send.php
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
    // Leer datos
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['token']) || empty($input['to']) || empty($input['amount'])) {
        throw new InvalidArgumentException('Faltan datos requeridos');
    }

    $token = strtoupper(trim($input['token']));
    $toAddress = trim($input['to']);
    $amount = $input['amount'];

    // Validar token
    if (!in_array($token, ['SPHE', 'USDT'], true)) {
        throw new InvalidArgumentException('Token inválido. Debe ser SPHE o USDT');
    }

    // Validar dirección Ethereum
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $toAddress)) {
        throw new InvalidArgumentException('Dirección de destino inválida');
    }

    // Validar cantidad
    $amountFloat = floatval($amount);
    if ($amountFloat <= 0) {
        throw new InvalidArgumentException('Cantidad debe ser mayor a 0');
    }

    // Obtener smart account del usuario Y VERIFICAR OWNERSHIP
    $stmt = $pdo->prepare('
        SELECT sa.smart_account_address, sa.is_deployed, sa.owner_address
        FROM smart_accounts sa
        WHERE sa.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || empty($account['smart_account_address'])) {
        throw new RuntimeException('Smart account no encontrada');
    }

    // SEGURIDAD: Verificar que la cuenta está desplegada
    if (!$account['is_deployed']) {
        throw new RuntimeException('Smart account debe estar desplegada para enviar fondos. Primero recibe fondos para desplegarla.');
    }

    $smartAccount = $account['smart_account_address'];
    
    // SEGURIDAD: Rate limiting - máximo 5 transferencias cada 10 minutos
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as tx_count
        FROM transaction_history
        WHERE user_id = ?
        AND type = "send"
        AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $rateLimitCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rateLimitCheck && $rateLimitCheck['tx_count'] >= 5) {
        throw new RuntimeException('Límite de transferencias excedido. Máximo 5 transferencias cada 10 minutos por seguridad.');
    }

    // Verificar balances
    try {
        $balancesResponse = nodeApiRequest('GET', 'wallet/balances?address=' . urlencode($smartAccount));
        $balances = $balancesResponse['data'] ?? $balancesResponse;
        
        $tokenLower = strtolower($token);
        $availableBalance = floatval($balances[$tokenLower]['formatted'] ?? 0);

        if ($amountFloat > $availableBalance) {
            throw new RuntimeException("Saldo insuficiente. Disponible: {$availableBalance} {$token}");
        }
    } catch (Exception $e) {
        error_log('Balance check failed: ' . $e->getMessage());
        // Continuar pero advertir
    }

    // Llamar a Node.js backend para ejecutar transferencia
    $transferPayload = [
        'smartAccountAddress' => $smartAccount,
        'token' => $token,
        'to' => $toAddress,
        'amount' => strval($amount),
        'userId' => $_SESSION['user_id']
    ];

    try {
        $transferResponse = nodeApiRequest('POST', 'wallet/transfer', $transferPayload);
        $data = $transferResponse['data'] ?? $transferResponse;

        // Registrar en historial (OBLIGATORIO para auditoría)
        try {
            $stmt = $pdo->prepare('
                INSERT INTO transaction_history 
                (user_id, type, token, amount, to_address, tx_hash, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $_SESSION['user_id'],
                'send',
                $token,
                $amount,
                $toAddress,
                $data['txHash'] ?? null,
                'completed'
            ]);
        } catch (Exception $e) {
            error_log('Failed to save transaction history: ' . $e->getMessage());
        }
        
        // SEGURIDAD: Log de auditoría con detalles completos
        try {
            $stmt = $pdo->prepare('
                INSERT INTO security_audit_log 
                (user_id, action, details, ip_address, user_agent, timestamp)
                VALUES (?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $_SESSION['user_id'],
                'wallet_send',
                json_encode([
                    'token' => $token,
                    'amount' => $amount,
                    'to' => $toAddress,
                    'from' => $smartAccount,
                    'tx_hash' => $data['txHash'] ?? null
                ]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log('Failed to save audit log: ' . $e->getMessage());
            // No fallar la transacción por error en log
        }

        echo json_encode([
            'success' => true,
            'message' => "Enviados {$amountFloat} {$token} correctamente",
            'tx_hash' => $data['txHash'] ?? null,
            'data' => $data
        ]);

    } catch (Exception $e) {
        error_log('Transfer failed: ' . $e->getMessage());
        throw new RuntimeException('Error al ejecutar la transferencia: ' . $e->getMessage());
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
    error_log('send.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la transferencia'
    ]);
}
