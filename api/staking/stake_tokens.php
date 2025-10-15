<?php
/**
 * API: Registrar depósito de staking
 * Endpoint: /api/staking/stake_tokens.php
 * Método: POST
 * Descripción: Registra un nuevo depósito de staking en la base de datos después de confirmar la transacción
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
    $required = ['user_id', 'amount', 'pool_id', 'tx_hash'];
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
    $pool_id = (int)$data['pool_id'];
    $tx_hash = $data['tx_hash'];

    // Validaciones
    if ($user_id <= 0) {
        throw new Exception('ID de usuario inválido');
    }

    if (!is_numeric($amount) || $amount <= 0) {
        throw new Exception('Monto inválido');
    }

    if ($pool_id < 0 || $pool_id > 3) {
        throw new Exception('Pool ID inválido');
    }

    if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash)) {
        throw new Exception('Hash de transacción inválido');
    }

    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT id, wallet_address FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }

    // Verificar que no existe ya este tx_hash
    $stmt = $pdo->prepare("SELECT id FROM staking_deposits WHERE tx_hash = ?");
    $stmt->execute([$tx_hash]);
    if ($stmt->fetch()) {
        throw new Exception('Esta transacción ya fue registrada');
    }

    // Verificar que el pool existe y está activo
    $stmt = $pdo->prepare("SELECT * FROM staking_pools_info WHERE pool_id = ? AND is_active = TRUE");
    $stmt->execute([$pool_id]);
    $pool = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pool) {
        throw new Exception('Pool no encontrado o inactivo');
    }

    // Verificar monto mínimo
    if ($amount < $pool['min_stake']) {
        throw new Exception("Monto mínimo para este pool: {$pool['min_stake']} SPHE");
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Insertar depósito
        $stmt = $pdo->prepare("
            INSERT INTO staking_deposits 
            (user_id, amount, pool_id, tx_hash, status) 
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$user_id, $amount, $pool_id, $tx_hash]);
        $deposit_id = $pdo->lastInsertId();

        // Registrar en log de transacciones
        $stmt = $pdo->prepare("
            INSERT INTO staking_transactions_log 
            (user_id, transaction_type, amount, pool_id, tx_hash, status) 
            VALUES (?, 'stake', ?, ?, ?, 'confirmed')
        ");
        $stmt->execute([$user_id, $amount, $pool_id, $tx_hash]);

        // Las estadísticas se actualizan automáticamente por triggers

        $pdo->commit();

        // Obtener estadísticas actualizadas
        $stmt = $pdo->prepare("
            SELECT 
                current_staked,
                total_staked,
                total_rewards_claimed,
                active_deposits_count
            FROM staking_stats 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Respuesta exitosa
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Depósito registrado exitosamente',
            'data' => [
                'deposit_id' => $deposit_id,
                'user_id' => $user_id,
                'amount' => $amount,
                'pool_id' => $pool_id,
                'pool_name' => $pool['name'],
                'tx_hash' => $tx_hash,
                'staked_at' => date('Y-m-d H:i:s'),
                'stats' => $stats
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en stake_tokens.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
