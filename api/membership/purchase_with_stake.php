<?php
// ============================================
// PURCHASE MEMBERSHIP WITH STAKING
// ============================================
// Procesa compra de membresía con sistema 50% pago + 50% stake

require_once __DIR__ . '/../../config/connection.php';

// Rate limiting
require_once __DIR__ . '/../helpers/rate_limiter.php';
if (!checkRateLimit($_SERVER['REMOTE_ADDR'] . '_membership_purchase', 3, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many purchase attempts. Please wait a minute.'
    ]);
    exit;
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
    $stake_tx_hash = $input['stake_tx_hash'] ?? ''; // TX del smart contract de staking
    $stake_id_on_contract = $input['stake_id_on_contract'] ?? null; // ID del stake en el contrato

    // Validar plan
    $valid_plans = ['platinum', 'gold', 'diamond', 'creator'];
    if (!in_array($plan, $valid_plans)) {
        throw new Exception('Plan inválido');
    }

    // Validar wallet address
    if (!$wallet_address || !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
        throw new Exception('Dirección de wallet inválida');
    }

    // Validar stake_tx_hash
    if (!$stake_tx_hash || !preg_match('/^0x[a-fA-F0-9]{64}$/', $stake_tx_hash)) {
        throw new Exception('Hash de transacción de staking inválido');
    }

    // Precios de planes
    $plan_prices = [
        'free' => 0,
        'platinum' => 100,
        'gold' => 250,
        'diamond' => 500,
        'creator' => 750
    ];

    $plan_order = ['free', 'platinum', 'gold', 'diamond', 'creator'];

    // Obtener plan actual del usuario
    $stmt = $pdo->prepare("SELECT membership_plan, wallet_address FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $current_plan_data = $stmt->fetch();
    $current_plan = $current_plan_data['membership_plan'] ?? 'free';
    $stored_wallet = $current_plan_data['wallet_address'] ?? '';

    // Verificar que wallet_address coincida con el registrado
    if ($stored_wallet && strtolower($stored_wallet) !== strtolower($wallet_address)) {
        throw new Exception('La wallet no coincide con la registrada en tu cuenta');
    }

    // Verificar que no es un downgrade
    $current_index = array_search($current_plan, $plan_order);
    $new_index = array_search($plan, $plan_order);

    if ($new_index <= $current_index) {
        throw new Exception('Ya tienes este plan o uno superior');
    }

    // Calcular precios
    $total_price = $plan_prices[$plan];
    $payment_amount = $total_price / 2;
    $stake_amount = $total_price / 2;

    // Verificar que la transacción no ha sido usada antes
    $stmt = $pdo->prepare("
        SELECT id FROM membership_stakes
        WHERE blockchain_stake_tx = ?
    ");
    $stmt->execute([$stake_tx_hash]);

    if ($stmt->fetch()) {
        throw new Exception('Esta transacción ya fue usada para una compra anterior');
    }

    // Verificar transacción en blockchain (opcional - requiere Web3Helper)
    $web3_helper_path = __DIR__ . '/../../escrow-system/helpers/Web3Helper.php';
    $blockchain_config_path = __DIR__ . '/../../escrow-system/config/blockchain_config.php';

    if (file_exists($blockchain_config_path) && file_exists($web3_helper_path)) {
        require_once $blockchain_config_path;
        require_once $web3_helper_path;

        if (class_exists('Web3Helper')) {
            $web3 = new Web3Helper();
            $verification = $web3->verifyTransaction($stake_tx_hash);

            if (!$verification || !$verification['success']) {
                throw new Exception('No se pudo verificar la transacción en blockchain');
            }

            $confirmations = $verification['confirmations'] ?? 0;
            if ($confirmations < 1) {
                throw new Exception('La transacción aún no tiene suficientes confirmaciones');
            }
        }
    }

    // Iniciar transacción SQL
    $pdo->beginTransaction();

    try {
        // 1. Registrar transacción de membresía
        $stmt = $pdo->prepare("
            INSERT INTO membership_transactions
            (user_id, plan_type, amount, wallet_address, blockchain_tx_hash, confirmed, status, created_at, confirmed_at)
            VALUES (?, ?, ?, ?, ?, TRUE, 'confirmed', NOW(), NOW())
        ");
        $stmt->execute([$user_id, $plan, $total_price, $wallet_address, $stake_tx_hash]);
        $transaction_id = $pdo->lastInsertId();

        // 2. Registrar stake
        $unlock_date = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $pdo->prepare("
            INSERT INTO membership_stakes
            (user_id, transaction_id, plan_type, staked_amount, unlock_date, claimed, blockchain_stake_tx, stake_id_on_contract, created_at)
            VALUES (?, ?, ?, ?, ?, FALSE, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $transaction_id,
            $plan,
            $stake_amount,
            $unlock_date,
            $stake_tx_hash,
            $stake_id_on_contract
        ]);
        $stake_id = $pdo->lastInsertId();

        // 3. Actualizar plan del usuario
        $expires = date('Y-m-d H:i:s', strtotime('+1 month'));

        $stmt = $pdo->prepare("
            UPDATE users
            SET membership_plan = ?,
                membership_expires = ?,
                wallet_address = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$plan, $expires, $wallet_address, $user_id]);

        // 4. Registrar en sphe_transactions
        $stmt = $pdo->prepare("
            INSERT INTO sphe_transactions
            (from_user_id, to_user_id, transaction_type, amount, description, reference_type, reference_id, blockchain_tx_hash, status, created_at)
            VALUES (?, NULL, 'membership_purchase', ?, ?, 'membership_stake', ?, ?, 'completed', NOW())
        ");

        $description = "Compra de membresía {$plan} (50% pago + 50% stake)";
        $stmt->execute([$user_id, $total_price, $description, $stake_id, $stake_tx_hash]);

        // 5. Crear notificación
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications
                (user_id, type, title, message, created_at)
                VALUES (?, 'membership', 'Membresía Activada', ?, NOW())
            ");

            $notification_msg = "Tu plan {$plan} ha sido activado. {$stake_amount} SPHE estarán disponibles para reclamar el " . date('d/m/Y', strtotime($unlock_date));
            $stmt->execute([$user_id, $notification_msg]);
        } catch (Exception $e) {
            // Notificación es opcional
            error_log("Error sending notification: " . $e->getMessage());
        }

        // Confirmar transacción
        $pdo->commit();

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Membresía activada con sistema de staking',
            'data' => [
                'plan' => $plan,
                'previous_plan' => $current_plan,
                'total_paid' => $total_price,
                'payment_amount' => $payment_amount,
                'stake_amount' => $stake_amount,
                'membership_expires' => $expires,
                'stake_unlock_date' => $unlock_date,
                'stake_id' => $stake_id,
                'transaction_id' => $transaction_id,
                'tx_hash' => $stake_tx_hash,
                'wallet_address' => $wallet_address
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
