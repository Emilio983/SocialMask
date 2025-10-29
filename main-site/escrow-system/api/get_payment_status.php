<?php
/**
 * GET PAYMENT STATUS API
 * Verifica el estado de un pago por tx_hash
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Incluir configuración
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../config/blockchain_config.php';
require_once __DIR__ . '/../helpers/Web3Helper.php';

if (!isset($_GET['tx_hash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tx_hash parameter is required']);
    exit;
}

$tx_hash = strtolower(trim($_GET['tx_hash']));

if (!isValidTxHash($tx_hash)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid transaction hash']);
    exit;
}

try {
    // Buscar el pago en la base de datos
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            s.title as survey_title,
            s.status as survey_status
        FROM payments p
        LEFT JOIN surveys s ON p.survey_id = s.id
        WHERE p.tx_hash = :tx_hash
    ");
    $stmt->execute([':tx_hash' => $tx_hash]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment not found']);
        exit;
    }

    // Si ya está confirmado, retornar directamente
    if ($payment['confirmed']) {
        echo json_encode([
            'success' => true,
            'payment' => $payment,
            'status' => 'confirmed',
            'explorer_url' => getExplorerTxUrl($tx_hash)
        ]);
        exit;
    }

    // Si no está confirmado, verificar en blockchain
    $web3 = new Web3Helper();
    $verification = $web3->verifyTransaction($tx_hash);

    if (!$verification) {
        echo json_encode([
            'success' => true,
            'payment' => $payment,
            'status' => 'pending',
            'confirmations' => 0,
            'min_required' => MIN_CONFIRMATIONS,
            'message' => 'Transaction is pending on blockchain',
            'explorer_url' => getExplorerTxUrl($tx_hash)
        ]);
        exit;
    }

    if (!$verification['success']) {
        echo json_encode([
            'success' => true,
            'payment' => $payment,
            'status' => 'failed',
            'message' => 'Transaction failed on blockchain',
            'explorer_url' => getExplorerTxUrl($tx_hash)
        ]);
        exit;
    }

    $confirmations = $verification['confirmations'];

    // Si alcanzó las confirmaciones mínimas, actualizar en BD
    if ($confirmations >= MIN_CONFIRMATIONS) {
        $stmt = $pdo->prepare("
            UPDATE payments
            SET
                confirmed = TRUE,
                confirmed_at = NOW(),
                block_number = :block_number,
                confirmations = :confirmations,
                gas_used = :gas_used
            WHERE id = :payment_id
        ");

        $stmt->execute([
            ':block_number' => $verification['block_number'],
            ':confirmations' => $confirmations,
            ':gas_used' => $verification['gas_used'],
            ':payment_id' => $payment['id']
        ]);

        echo json_encode([
            'success' => true,
            'payment' => array_merge($payment, [
                'confirmed' => true,
                'confirmations' => $confirmations
            ]),
            'status' => 'confirmed',
            'message' => 'Payment confirmed!',
            'explorer_url' => getExplorerTxUrl($tx_hash)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'payment' => $payment,
            'status' => 'pending',
            'confirmations' => $confirmations,
            'min_required' => MIN_CONFIRMATIONS,
            'message' => "Waiting for confirmations ({$confirmations}/" . MIN_CONFIRMATIONS . ")",
            'explorer_url' => getExplorerTxUrl($tx_hash)
        ]);
    }

} catch (PDOException $e) {
    error_log("Get payment status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check payment status'
    ]);
}
