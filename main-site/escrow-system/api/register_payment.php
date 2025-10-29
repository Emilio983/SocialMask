<?php
/**
 * REGISTER PAYMENT API
 * Registra un pago/depósito realizado por un participante
 * El frontend llama a esta API después de que el usuario envía la transacción
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session for user tracking
session_start();

// Incluir configuración
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../config/blockchain_config.php';
require_once __DIR__ . '/../helpers/Web3Helper.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

// Validar datos requeridos
$required = ['survey_id', 'tx_hash', 'from_address', 'amount'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Field '{$field}' is required"]);
        exit;
    }
}

$survey_id = intval($input['survey_id']);
$tx_hash = strtolower(trim($input['tx_hash']));
$from_address = strtolower(trim($input['from_address']));
$amount = $input['amount']; // En Wei (string grande)

// Validar formato de tx_hash
if (!isValidTxHash($tx_hash)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid transaction hash format']);
    exit;
}

// Validar formato de address
if (!isValidAddress($from_address)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid wallet address format']);
    exit;
}

try {
    // Verificar que la encuesta existe y está activa
    $stmt = $pdo->prepare("
        SELECT
            id,
            contract_address,
            survey_id_on_chain,
            price,
            status,
            close_date,
            max_participants,
            total_prize_pool
        FROM surveys
        WHERE id = :survey_id
    ");
    $stmt->execute([':survey_id' => $survey_id]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$survey) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Survey not found']);
        exit;
    }

    if ($survey['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey is not active']);
        exit;
    }

    if (strtotime($survey['close_date']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey has closed']);
        exit;
    }

    // Verificar si el tx_hash ya está registrado
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE tx_hash = :tx_hash");
    $stmt->execute([':tx_hash' => $tx_hash]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction already registered']);
        exit;
    }

    // Verificar si el participante ya pagó esta encuesta
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM payments
        WHERE survey_id = :survey_id
          AND from_address = :from_address
          AND confirmed = TRUE
    ");
    $stmt->execute([
        ':survey_id' => $survey_id,
        ':from_address' => $from_address
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You have already paid for this survey']);
        exit;
    }

    // Verificar límite de participantes
    if ($survey['max_participants']) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT from_address) as count
            FROM payments
            WHERE survey_id = :survey_id
              AND confirmed = TRUE
        ");
        $stmt->execute([':survey_id' => $survey_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] >= $survey['max_participants']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Survey has reached maximum participants']);
            exit;
        }
    }

    // Buscar user_id si el wallet está registrado
    $user_id = null;
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE wallet_address = :wallet");
    $stmt->execute([':wallet' => $from_address]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_id = $user['user_id'];
    }

    // Insertar el pago como PENDIENTE (el cron job lo verificará)
    $stmt = $pdo->prepare("
        INSERT INTO payments (
            survey_id,
            tx_hash,
            from_address,
            to_address,
            amount,
            user_id,
            confirmed
        ) VALUES (
            :survey_id,
            :tx_hash,
            :from_address,
            :to_address,
            :amount,
            :user_id,
            FALSE
        )
    ");

    $stmt->execute([
        ':survey_id' => $survey_id,
        ':tx_hash' => $tx_hash,
        ':from_address' => $from_address,
        ':to_address' => $survey['contract_address'],
        ':amount' => $amount,
        ':user_id' => $user_id
    ]);

    $payment_id = $pdo->lastInsertId();

    // Opcionalmente, verificar inmediatamente la transacción
    $web3 = new Web3Helper();
    $verification = $web3->verifyTransaction($tx_hash);

    $status = 'pending';
    $confirmations = 0;

    if ($verification && $verification['success']) {
        $confirmations = $verification['confirmations'];

        // Si ya tiene suficientes confirmaciones, marcar como confirmado
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
                ':payment_id' => $payment_id
            ]);

            $status = 'confirmed';
        }
    }

    echo json_encode([
        'success' => true,
        'payment_id' => $payment_id,
        'status' => $status,
        'confirmations' => $confirmations,
        'min_confirmations_required' => MIN_CONFIRMATIONS,
        'message' => 'Payment registered. It will be confirmed automatically once blockchain confirmations are received.',
        'explorer_url' => getExplorerTxUrl($tx_hash)
    ]);

} catch (PDOException $e) {
    error_log("Register payment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to register payment'
    ]);
}
