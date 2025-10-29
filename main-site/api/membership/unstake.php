<?php
// ============================================
// UNSTAKE - Reclamar stake después de 30 días
// ============================================

require_once __DIR__ . '/../../config/connection.php';

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

    $stake_id = $input['stake_id'] ?? null;
    $claim_tx_hash = $input['claim_tx_hash'] ?? null; // TX hash del unstake en blockchain

    if (!$stake_id) {
        throw new Exception('ID de stake requerido');
    }

    // Validar claim_tx_hash si se proporciona
    if ($claim_tx_hash && !preg_match('/^0x[a-fA-F0-9]{64}$/', $claim_tx_hash)) {
        throw new Exception('Hash de transacción inválido');
    }

    // Obtener información del stake
    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            plan_type,
            staked_amount,
            unlock_date,
            claimed,
            blockchain_stake_tx,
            stake_id_on_contract
        FROM membership_stakes
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$stake_id, $user_id]);
    $stake = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stake) {
        throw new Exception('Stake no encontrado');
    }

    // Verificar que no ha sido reclamado
    if ($stake['claimed']) {
        throw new Exception('Este stake ya fue reclamado');
    }

    // Verificar que ya pasaron los 30 días
    $unlock_time = strtotime($stake['unlock_date']);
    $now = time();

    if ($now < $unlock_time) {
        $days_left = ceil(($unlock_time - $now) / 86400);
        throw new Exception("El stake aún está bloqueado. Quedan {$days_left} días para poder reclamarlo.");
    }

    // Si se proporciona claim_tx_hash, verificar en blockchain
    if ($claim_tx_hash) {
        $web3_helper_path = __DIR__ . '/../../escrow-system/helpers/Web3Helper.php';
        $blockchain_config_path = __DIR__ . '/../../escrow-system/config/blockchain_config.php';

        if (file_exists($blockchain_config_path) && file_exists($web3_helper_path)) {
            require_once $blockchain_config_path;
            require_once $web3_helper_path;

            if (class_exists('Web3Helper')) {
                $web3 = new Web3Helper();
                $verification = $web3->verifyTransaction($claim_tx_hash);

                if (!$verification || !$verification['success']) {
                    throw new Exception('No se pudo verificar la transacción de unstake en blockchain');
                }
            }
        }
    }

    // Marcar stake como reclamado
    $stmt = $pdo->prepare("
        UPDATE membership_stakes
        SET claimed = TRUE,
            blockchain_claim_tx = ?,
            claimed_at = NOW()
        WHERE id = ? AND user_id = ?
    ");

    $result = $stmt->execute([$claim_tx_hash, $stake_id, $user_id]);

    if (!$result) {
        throw new Exception('Error al procesar el unstake');
    }

    // Registrar transacción en sphe_transactions
    $stmt = $pdo->prepare("
        INSERT INTO sphe_transactions
        (from_user_id, to_user_id, transaction_type, amount, description, reference_type, reference_id, blockchain_tx_hash, status, created_at)
        VALUES (NULL, ?, 'unstake', ?, ?, 'membership_stake', ?, ?, 'completed', NOW())
    ");

    $description = "Unstake de membresía {$stake['plan_type']}";
    $stmt->execute([
        $user_id,
        $stake['staked_amount'],
        $description,
        $stake_id,
        $claim_tx_hash
    ]);

    // Crear notificación
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications
            (user_id, type, title, message, created_at)
            VALUES (?, 'stake', 'Stake Reclamado', ?, NOW())
        ");

        $notification_msg = "Has reclamado exitosamente {$stake['staked_amount']} SPHE de tu stake de membresía {$stake['plan_type']}";
        $stmt->execute([$user_id, $notification_msg]);
    } catch (Exception $e) {
        // Notificación es opcional
        error_log("Error sending notification: " . $e->getMessage());
    }

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Stake reclamado exitosamente',
        'data' => [
            'stake_id' => $stake_id,
            'amount' => $stake['staked_amount'],
            'plan_type' => $stake['plan_type'],
            'claimed_at' => date('Y-m-d H:i:s'),
            'claim_tx_hash' => $claim_tx_hash
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
