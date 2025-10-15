<?php
// ============================================
// UPDATE PLAN - Actualizar plan de membresía con VERIFICACIÓN BLOCKCHAIN
// ============================================

require_once '../config/connection.php';

// Rate limiting - Max 3 payment attempts per minute per IP
require_once __DIR__ . '/helpers/rate_limiter.php';
if (!checkRateLimit($_SERVER['REMOTE_ADDR'] . '_payment', 3, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many payment attempts. Please wait a minute and try again.'
    ]);
    exit;
}

// Cargar Web3Helper para verificar transacciones
$web3_helper_path = __DIR__ . '/../escrow-system/helpers/Web3Helper.php';
$blockchain_config_path = __DIR__ . '/../escrow-system/config/blockchain_config.php';

if (file_exists($blockchain_config_path) && file_exists($web3_helper_path)) {
    require_once $blockchain_config_path;
    require_once $web3_helper_path;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Verificar sesión activa
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    $user_id = $_SESSION['user_id'];

    // Leer datos del request
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Datos inválidos');
    }

    $plan = $input['plan'] ?? '';
    $wallet_address = $input['wallet_address'] ?? '';
    $tx_hash = $input['tx_hash'] ?? ''; // NUEVO: tx_hash es REQUERIDO

    // Validar plan
    $valid_plans = ['platinum', 'gold', 'diamond', 'creator'];
    if (!in_array($plan, $valid_plans)) {
        throw new Exception('Plan inválido');
    }

    // Validar wallet address
    if (!$wallet_address || !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
        throw new Exception('Dirección de wallet inválida');
    }

    // CRÍTICO: Validar tx_hash
    if (!$tx_hash || !preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash)) {
        throw new Exception('Hash de transacción inválido. Debes enviar la transacción primero.');
    }

    // Obtener precios dinámicos desde la base de datos
    require_once __DIR__ . '/helpers/pricing.php';
    $pricing = new PricingHelper();
    $plan_prices = $pricing->getAllMembershipPrices();

    $plan_order = ['free', 'platinum', 'gold', 'diamond', 'creator'];

    // Obtener plan actual del usuario
    $stmt = $pdo->prepare("SELECT membership_plan, wallet_address FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $current_plan_data = $stmt->fetch();
    $current_plan = $current_plan_data['membership_plan'] ?? 'free';
    $stored_wallet = $current_plan_data['wallet_address'] ?? '';

    // Verificar que wallet_address coincida con el registrado (si ya hay uno)
    if ($stored_wallet && strtolower($stored_wallet) !== strtolower($wallet_address)) {
        throw new Exception('La wallet no coincide con la registrada en tu cuenta');
    }

    // Calcular precio a pagar (solo la diferencia si es upgrade)
    $current_price = $plan_prices[$current_plan];
    $new_price = $plan_prices[$plan];
    $price_to_pay = max(0, $new_price - $current_price);

    // Verificar que no es un downgrade
    $current_index = array_search($current_plan, $plan_order);
    $new_index = array_search($plan, $plan_order);

    if ($new_index < $current_index) {
        throw new Exception('No puedes hacer downgrade de tu plan. Contacta a soporte.');
    }

    if ($current_plan === $plan) {
        throw new Exception('Ya tienes este plan activo.');
    }

    // Verificar si esta transacción ya fue usada
    $stmt = $pdo->prepare("
        SELECT id, status FROM membership_transactions
        WHERE blockchain_tx_hash = ?
    ");
    $stmt->execute([$tx_hash]);
    $existing_tx = $stmt->fetch();

    if ($existing_tx) {
        if ($existing_tx['status'] === 'confirmed') {
            throw new Exception('Esta transacción ya fue usada para una compra anterior');
        } else if ($existing_tx['status'] === 'pending') {
            // Si está pendiente, intentar verificar de nuevo
            $transaction_id = $existing_tx['id'];
        }
    }

    // ===================================
    // VERIFICAR TRANSACCIÓN EN BLOCKCHAIN
    // ===================================

    if (!class_exists('Web3Helper')) {
        throw new Exception('Sistema de verificación blockchain no disponible. Contacta a soporte.');
    }

    $web3 = new Web3Helper();
    $verification = $web3->verifyTransaction($tx_hash);

    if (!$verification) {
        throw new Exception('Transacción no encontrada en blockchain. Espera unos segundos e intenta de nuevo.');
    }

    if (!$verification['success']) {
        throw new Exception('La transacción falló en blockchain. Verifica en PolygonScan.');
    }

    $confirmations = $verification['confirmations'] ?? 0;
    $block_number = $verification['block_number'] ?? null;

    // Requerir al menos 1 confirmación (puede ajustarse)
    $min_confirmations = 1;
    if ($confirmations < $min_confirmations) {
        throw new Exception("Transacción pendiente de confirmación ({$confirmations}/{$min_confirmations}). Espera unos segundos.");
    }

    // Obtener detalles de la transacción
    $tx_details = $web3->getTransaction($tx_hash);

    if (!$tx_details) {
        throw new Exception('No se pudo obtener detalles de la transacción');
    }

    // Verificar que la transacción es desde la wallet del usuario
    if (strtolower($tx_details['from']) !== strtolower($wallet_address)) {
        throw new Exception('La transacción no proviene de tu wallet');
    }

    // ===================================
    // TODO VERIFICADO - ACTUALIZAR PLAN
    // ===================================

    if (!isset($transaction_id)) {
        // Registrar la transacción como confirmada
        $stmt = $pdo->prepare("
            INSERT INTO membership_transactions
            (user_id, plan_type, amount, wallet_address, blockchain_tx_hash, confirmed, block_number, status, created_at, confirmed_at)
            VALUES (?, ?, ?, ?, ?, TRUE, ?, 'confirmed', NOW(), NOW())
        ");
        $stmt->execute([$user_id, $plan, $price_to_pay, $wallet_address, $tx_hash, $block_number]);
        $transaction_id = $pdo->lastInsertId();
    } else {
        // Actualizar transacción existente
        $stmt = $pdo->prepare("
            UPDATE membership_transactions
            SET confirmed = TRUE,
                block_number = ?,
                status = 'confirmed',
                confirmed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$block_number, $transaction_id]);
    }

    // Actualizar plan del usuario
    $stmt = $pdo->prepare("
        UPDATE users
        SET membership_plan = ?,
            membership_expires = DATE_ADD(NOW(), INTERVAL 1 MONTH),
            wallet_address = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");

    $result = $stmt->execute([$plan, $wallet_address, $user_id]);

    if (!$result) {
        throw new Exception('Error al actualizar el plan');
    }

    // Registrar transacción en sphe_transactions para tracking general
    $stmt = $pdo->prepare("
        INSERT INTO sphe_transactions
        (from_user_id, to_user_id, transaction_type, amount, description, reference_type, reference_id, blockchain_tx_hash, status, created_at)
        VALUES (?, NULL, 'purchase', ?, ?, 'membership', ?, ?, 'completed', NOW())
    ");

    $description = "Compra de membresía {$plan}";
    $stmt->execute([$user_id, $price_to_pay, $description, $transaction_id, $tx_hash]);

    // Opcional: Enviar notificación
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications
            (user_id, type, title, message, created_at)
            VALUES (?, 'membership', 'Plan Actualizado', ?, NOW())
        ");

        $notification_msg = "Tu plan {$plan} ha sido activado exitosamente. Válido hasta " . date('Y-m-d', strtotime('+1 month'));
        $stmt->execute([$user_id, $notification_msg]);
    } catch (Exception $e) {
        // Notificación es opcional, no fallar si hay error
        error_log("Error sending notification: " . $e->getMessage());
    }

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Plan actualizado exitosamente y verificado en blockchain',
        'plan' => $plan,
        'previous_plan' => $current_plan,
        'amount_paid' => $price_to_pay,
        'is_upgrade' => $price_to_pay < $new_price,
        'expires' => date('Y-m-d H:i:s', strtotime('+1 month')),
        'tx_hash' => $tx_hash,
        'confirmations' => $confirmations,
        'transaction_id' => $transaction_id,
        'verified_on_blockchain' => true
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'verified_on_blockchain' => false
    ]);
}
?>
