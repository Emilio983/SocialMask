<?php
/**
 * API: Procesar Pagos con Smart Wallet
 * POST /api/payments/process_payment.php
 * Sistema unificado de pagos gasless usando smart accounts
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
    
    if (!$input || empty($input['type']) || empty($input['amount'])) {
        throw new InvalidArgumentException('Faltan datos requeridos: type y amount son obligatorios');
    }

    $paymentType = strtoupper(trim($input['type'])); // MEMBERSHIP, GROUP_CREATION, DONATION, etc
    $amount = floatval($input['amount']);
    $token = isset($input['token']) ? strtoupper(trim($input['token'])) : 'SPHE';
    $metadata = $input['metadata'] ?? [];

    // Validar token
    if (!in_array($token, ['SPHE', 'USDT'], true)) {
        throw new InvalidArgumentException('Token inválido. Debe ser SPHE o USDT');
    }

    // Validar cantidad
    if ($amount <= 0) {
        throw new InvalidArgumentException('Cantidad debe ser mayor a 0');
    }

    // Validar tipo de pago
    $validTypes = ['MEMBERSHIP', 'GROUP_CREATION', 'DONATION', 'STAKING', 'GOVERNANCE', 'CUSTOM'];
    if (!in_array($paymentType, $validTypes, true)) {
        throw new InvalidArgumentException('Tipo de pago inválido');
    }

    // Obtener smart account del usuario
    $stmt = $pdo->prepare('
        SELECT sa.smart_account_address, sa.is_deployed, sa.owner_address, u.username
        FROM smart_accounts sa
        JOIN users u ON sa.user_id = u.user_id
        WHERE sa.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || empty($account['smart_account_address'])) {
        throw new RuntimeException('Smart account no encontrada. Por favor contacta a soporte.');
    }

    $smartAccount = $account['smart_account_address'];
    $username = $account['username'];
    
    // Verificar balance antes de proceder
    try {
        $balancesResponse = nodeApiRequest('GET', 'wallet/balances?address=' . urlencode($smartAccount));
        $balances = $balancesResponse['data'] ?? $balancesResponse;
        
        $tokenLower = strtolower($token);
        $currentBalance = 0;
        
        if (isset($balances[$tokenLower]) && isset($balances[$tokenLower]['formatted'])) {
            $currentBalance = floatval($balances[$tokenLower]['formatted']);
        }
        
        if ($currentBalance < $amount) {
            throw new RuntimeException("Balance insuficiente. Necesitas {$amount} {$token} pero solo tienes {$currentBalance} {$token}");
        }
    } catch (Exception $e) {
        error_log("Error verificando balance: " . $e->getMessage());
        throw new RuntimeException('Error verificando balance. Por favor intenta de nuevo.');
    }

    // Dirección del treasury (donde van los pagos)
    $treasuryAddress = '0xa1052872c755B5B2192b54ABD5F08546eeE6aa20';
    
    // Preparar pago gasless
    $payload = [
        'userId' => $_SESSION['user_id'],
        'smartAccountAddress' => $smartAccount,
        'recipient' => $treasuryAddress,
        'actionType' => 'PAYMENT',
        'token' => $token,
        'amount' => (string)$amount,
        'metadata' => json_encode([
            'payment_type' => $paymentType,
            'user_id' => $_SESSION['user_id'],
            'username' => $username,
            'timestamp' => time(),
            'additional_data' => $metadata
        ])
    ];

    // Ejecutar transacción gasless a través del relayer
    try {
        $txResponse = nodeApiRequest('POST', 'actions/payment', $payload);
        
        if (!isset($txResponse['success']) || !$txResponse['success']) {
            throw new RuntimeException($txResponse['message'] ?? 'Error ejecutando transacción');
        }

        $txHash = $txResponse['txHash'] ?? null;
        $taskId = $txResponse['taskId'] ?? null;

        // Registrar transacción en base de datos
        $stmt = $pdo->prepare('
            INSERT INTO wallet_transactions (
                user_id,
                smart_account_address,
                transaction_type,
                token,
                amount,
                recipient_address,
                tx_hash,
                task_id,
                status,
                payment_type,
                metadata,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $stmt->execute([
            $_SESSION['user_id'],
            $smartAccount,
            'payment',
            $token,
            $amount,
            $treasuryAddress,
            $txHash,
            $taskId,
            'pending',
            $paymentType,
            json_encode($metadata)
        ]);

        $transactionId = $pdo->lastInsertId();

        // Si es membership o group creation, procesarla inmediatamente
        if ($paymentType === 'MEMBERSHIP' && isset($metadata['plan'])) {
            processMembershipPayment($_SESSION['user_id'], $metadata['plan'], $transactionId, $amount, $token);
        } elseif ($paymentType === 'GROUP_CREATION' && isset($metadata['group_id'])) {
            processGroupCreationPayment($_SESSION['user_id'], $metadata['group_id'], $transactionId);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Pago procesado exitosamente',
            'data' => [
                'transaction_id' => $transactionId,
                'tx_hash' => $txHash,
                'task_id' => $taskId,
                'amount' => $amount,
                'token' => $token,
                'payment_type' => $paymentType,
                'status' => 'pending'
            ]
        ]);

    } catch (Exception $e) {
        error_log("Error ejecutando pago: " . $e->getMessage());
        throw new RuntimeException('Error procesando pago: ' . $e->getMessage());
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
} catch (Exception $e) {
    error_log("Error inesperado en process_payment: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}

/**
 * Procesar pago de membership
 */
function processMembershipPayment(int $userId, string $plan, int $transactionId, float $amount, string $token): void
{
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Actualizar plan del usuario
        $stmt = $pdo->prepare('
            UPDATE users 
            SET membership_plan = ?,
                membership_updated_at = NOW()
            WHERE user_id = ?
        ');
        $stmt->execute([$plan, $userId]);

        // Registrar en membership_transactions
        $stmt = $pdo->prepare('
            INSERT INTO membership_transactions (
                user_id,
                plan,
                amount,
                token,
                payment_transaction_id,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$userId, $plan, $amount, $token, $transactionId, 'completed']);

        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error procesando membership payment: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Procesar pago de creación de grupo
 */
function processGroupCreationPayment(int $userId, int $groupId, int $transactionId): void
{
    global $pdo;
    
    try {
        // Marcar el grupo como pagado/activo
        $stmt = $pdo->prepare('
            UPDATE communities 
            SET is_paid = 1,
                payment_transaction_id = ?,
                updated_at = NOW()
            WHERE community_id = ? AND creator_id = ?
        ');
        $stmt->execute([$transactionId, $groupId, $userId]);
        
    } catch (Exception $e) {
        error_log("Error procesando group creation payment: " . $e->getMessage());
        throw $e;
    }
}
