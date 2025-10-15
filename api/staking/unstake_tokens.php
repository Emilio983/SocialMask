<?php
/**
 * API: Registrar unstake de tokens
 * Endpoint: /api/staking/unstake_tokens.php
 * Método: POST
 * Descripción: Registra un unstake de tokens con sus rewards asociados
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
    $rewards = isset($data['rewards']) ? $data['rewards'] : 0;
    $tx_hash = $data['tx_hash'];
    $deposit_ids = isset($data['deposit_ids']) ? $data['deposit_ids'] : [];

    // Validaciones
    if ($user_id <= 0) {
        throw new Exception('ID de usuario inválido');
    }

    if (!is_numeric($amount) || $amount <= 0) {
        throw new Exception('Monto inválido');
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
    $stmt = $pdo->prepare("SELECT id FROM staking_transactions_log WHERE tx_hash = ?");
    $stmt->execute([$tx_hash]);
    if ($stmt->fetch()) {
        throw new Exception('Esta transacción ya fue registrada');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Verificar depósitos y validar lock period
        if (!empty($deposit_ids)) {
            // Verificar que todos los depósitos pueden ser unstakeados
            $placeholders = str_repeat('?,', count($deposit_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT 
                    d.id,
                    d.amount,
                    d.staked_at,
                    p.lock_period_days,
                    TIMESTAMPDIFF(SECOND, d.staked_at, NOW()) as staking_duration_seconds
                FROM staking_deposits d
                INNER JOIN staking_pools_info p ON d.pool_id = p.pool_id
                WHERE d.id IN ($placeholders) AND d.user_id = ? AND d.status = 'active'
            ");
            $stmt->execute(array_merge($deposit_ids, [$user_id]));
            $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($deposits) === 0) {
                throw new Exception('No se encontraron depósitos activos');
            }
            
            // Validar lock period de cada depósito
            foreach ($deposits as $deposit) {
                if ($deposit['lock_period_days'] > 0) {
                    $lock_seconds = $deposit['lock_period_days'] * 86400;
                    if ($deposit['staking_duration_seconds'] < $lock_seconds) {
                        $remaining_days = ceil(($lock_seconds - $deposit['staking_duration_seconds']) / 86400);
                        throw new Exception("Depósito ID {$deposit['id']} todavía está locked. Quedan {$remaining_days} días.");
                    }
                }
            }
            
            // Actualizar depósitos a unstaked
            $stmt = $pdo->prepare("
                UPDATE staking_deposits 
                SET status = 'unstaked', unstaked_at = NOW() 
                WHERE id IN ($placeholders) AND user_id = ? AND status = 'active'
            ");
            $stmt->execute(array_merge($deposit_ids, [$user_id]));
        } else {
            // Si no se especifican IDs, buscar el depósito más antiguo que esté unlocked
            $stmt = $pdo->prepare("
                SELECT 
                    d.id,
                    d.amount,
                    d.staked_at,
                    p.lock_period_days,
                    TIMESTAMPDIFF(SECOND, d.staked_at, NOW()) as staking_duration_seconds
                FROM staking_deposits d
                INNER JOIN staking_pools_info p ON d.pool_id = p.pool_id
                WHERE d.user_id = ? AND d.status = 'active'
                ORDER BY d.staked_at ASC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deposit) {
                throw new Exception('No se encontraron depósitos activos');
            }
            
            // Validar lock period
            if ($deposit['lock_period_days'] > 0) {
                $lock_seconds = $deposit['lock_period_days'] * 86400;
                if ($deposit['staking_duration_seconds'] < $lock_seconds) {
                    $remaining_days = ceil(($lock_seconds - $deposit['staking_duration_seconds']) / 86400);
                    throw new Exception("El stake todavía está locked. Quedan {$remaining_days} días.");
                }
            }
            
            // Actualizar depósito a unstaked
            $stmt = $pdo->prepare("
                UPDATE staking_deposits 
                SET status = 'unstaked', unstaked_at = NOW() 
                WHERE id = ? AND user_id = ? AND status = 'active'
            ");
            $stmt->execute([$deposit['id'], $user_id]);
        }

        // Registrar rewards si los hay
        if ($rewards > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO staking_rewards 
                (user_id, amount, reward_type, tx_hash) 
                VALUES (?, ?, 'unstake', ?)
            ");
            $stmt->execute([$user_id, $rewards, $tx_hash]);
        }

        // Registrar en log de transacciones
        $stmt = $pdo->prepare("
            INSERT INTO staking_transactions_log 
            (user_id, transaction_type, amount, tx_hash, status) 
            VALUES (?, 'unstake', ?, ?, 'confirmed')
        ");
        $stmt->execute([$user_id, $amount, $tx_hash]);

        $pdo->commit();

        // Obtener estadísticas actualizadas
        $stmt = $pdo->prepare("
            SELECT 
                current_staked,
                total_unstaked,
                total_rewards_claimed,
                active_deposits_count
            FROM staking_stats 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Respuesta exitosa
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Unstake registrado exitosamente',
            'data' => [
                'user_id' => $user_id,
                'amount_unstaked' => $amount,
                'rewards_claimed' => $rewards,
                'tx_hash' => $tx_hash,
                'unstaked_at' => date('Y-m-d H:i:s'),
                'stats' => $stats
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en unstake_tokens.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
