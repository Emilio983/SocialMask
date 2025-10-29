<?php
/**
 * API: Reclamar rewards de staking
 * Endpoint: /api/staking/claim_rewards.php
 * Método: POST
 * Descripción: Registra la reclamación de rewards sin hacer unstake
 */

require_once '../../config/config.php';
require_once '../cors_helper.php';
require_once '../response_helper.php';
require_once '../error_handler.php';

header('Content-Type: application/json');

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    // Obtener datos
    $data = json_decode(file_get_contents('php://input'), true);

    // Validar datos requeridos
    $required = ['user_id', 'amount', 'tx_hash'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Campo requerido faltante: $field"
            ]);
            exit;
        }
    }

    $user_id = (int)$data['user_id'];
    $amount = $data['amount'];
    $tx_hash = $data['tx_hash'];
    $deposit_id = isset($data['deposit_id']) ? (int)$data['deposit_id'] : null;

    // Validaciones
    if ($user_id <= 0) {
        throw new Exception('ID de usuario inválido');
    }

    if (!is_numeric($amount) || $amount <= 0) {
        throw new Exception('Monto de rewards inválido');
    }

    if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash)) {
        throw new Exception('Hash de transacción inválido');
    }

    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Usuario no encontrado');
    }

    // Verificar que no existe ya este tx_hash
    $stmt = $pdo->prepare("SELECT id FROM staking_rewards WHERE tx_hash = ?");
    $stmt->execute([$tx_hash]);
    if ($stmt->fetch()) {
        throw new Exception('Esta transacción de rewards ya fue registrada');
    }

    // Verificar que el usuario tiene depósitos activos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM staking_deposits 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] == 0) {
        throw new Exception('No tienes depósitos activos de staking');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Registrar reward
        $stmt = $pdo->prepare("
            INSERT INTO staking_rewards 
            (user_id, deposit_id, amount, reward_type, tx_hash) 
            VALUES (?, ?, ?, 'claim', ?)
        ");
        $stmt->execute([$user_id, $deposit_id, $amount, $tx_hash]);
        $reward_id = $pdo->lastInsertId();

        // Registrar en log de transacciones
        $stmt = $pdo->prepare("
            INSERT INTO staking_transactions_log 
            (user_id, transaction_type, amount, tx_hash, status) 
            VALUES (?, 'claim', ?, ?, 'confirmed')
        ");
        $stmt->execute([$user_id, $amount, $tx_hash]);

        $pdo->commit();

        // Obtener estadísticas actualizadas
        $stmt = $pdo->prepare("
            SELECT 
                current_staked,
                total_rewards_claimed,
                active_deposits_count,
                last_claim_at
            FROM staking_stats 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Respuesta exitosa
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Rewards reclamados exitosamente',
            'data' => [
                'reward_id' => $reward_id,
                'user_id' => $user_id,
                'amount' => $amount,
                'tx_hash' => $tx_hash,
                'claimed_at' => date('Y-m-d H:i:s'),
                'stats' => $stats
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en claim_rewards.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
