<?php
/**
 * API: Obtener información de staking de usuario
 * Endpoint: /api/staking/get_staking_info.php
 * Método: GET
 * Descripción: Retorna información completa del staking de un usuario
 */

require_once '../../config/config.php';
require_once '../cors_helper.php';
require_once '../response_helper.php';
require_once '../error_handler.php';

header('Content-Type: application/json');

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    // Obtener user_id
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    if ($user_id <= 0) {
        throw new Exception('ID de usuario inválido');
    }

    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT id, username, wallet_address FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }

    // Obtener estadísticas generales
    $stmt = $pdo->prepare("
        SELECT * FROM staking_stats WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si no tiene stats, crear registro vacío
    if (!$stats) {
        $stats = [
            'user_id' => $user_id,
            'total_staked' => '0.00000000',
            'total_unstaked' => '0.00000000',
            'total_rewards_claimed' => '0.00000000',
            'current_staked' => '0.00000000',
            'active_deposits_count' => 0,
            'total_deposits_count' => 0,
            'first_stake_at' => null,
            'last_stake_at' => null,
            'last_claim_at' => null
        ];
    }

    // Obtener depósitos activos por pool
    $stmt = $pdo->prepare("
        SELECT 
            d.pool_id,
            p.name as pool_name,
            p.lock_period_days,
            p.reward_multiplier,
            p.apy,
            SUM(d.amount) as total_in_pool,
            COUNT(*) as deposits_count,
            MIN(d.staked_at) as first_deposit,
            MAX(d.staked_at) as last_deposit
        FROM staking_deposits d
        INNER JOIN staking_pools_info p ON d.pool_id = p.pool_id
        WHERE d.user_id = ? AND d.status = 'active'
        GROUP BY d.pool_id, p.name, p.lock_period_days, p.reward_multiplier, p.apy
        ORDER BY d.pool_id
    ");
    $stmt->execute([$user_id]);
    $deposits_by_pool = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener últimos depósitos activos
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.amount,
            d.pool_id,
            p.name as pool_name,
            p.apy,
            d.tx_hash,
            d.staked_at,
            TIMESTAMPDIFF(SECOND, d.staked_at, NOW()) as staking_duration_seconds,
            CASE 
                WHEN p.lock_period_days > 0 THEN
                    DATE_ADD(d.staked_at, INTERVAL p.lock_period_days DAY)
                ELSE NULL
            END as unlock_date,
            CASE 
                WHEN p.lock_period_days > 0 THEN
                    (TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(d.staked_at, INTERVAL p.lock_period_days DAY)) > 0)
                ELSE FALSE
            END as is_locked
        FROM staking_deposits d
        INNER JOIN staking_pools_info p ON d.pool_id = p.pool_id
        WHERE d.user_id = ? AND d.status = 'active'
        ORDER BY d.staked_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $active_deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener últimas rewards reclamadas
    $stmt = $pdo->prepare("
        SELECT 
            id,
            amount,
            reward_type,
            tx_hash,
            claimed_at
        FROM staking_rewards
        WHERE user_id = ?
        ORDER BY claimed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular APY promedio ponderado
    $weighted_apy = 0;
    if ($stats['current_staked'] > 0) {
        foreach ($deposits_by_pool as $pool) {
            $weight = $pool['total_in_pool'] / $stats['current_staked'];
            $weighted_apy += $pool['apy'] * $weight;
        }
    }

    // Obtener información de todos los pools disponibles
    $stmt = $pdo->prepare("
        SELECT 
            pool_id,
            name,
            lock_period_days,
            reward_multiplier,
            min_stake,
            total_staked,
            participants_count,
            apy,
            is_active
        FROM staking_pools_info
        WHERE is_active = TRUE
        ORDER BY pool_id
    ");
    $stmt->execute();
    $available_pools = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'wallet_address' => $user['wallet_address']
            ],
            'summary' => [
                'current_staked' => $stats['current_staked'],
                'total_staked' => $stats['total_staked'],
                'total_unstaked' => $stats['total_unstaked'],
                'total_rewards_claimed' => $stats['total_rewards_claimed'],
                'active_deposits_count' => (int)$stats['active_deposits_count'],
                'total_deposits_count' => (int)$stats['total_deposits_count'],
                'weighted_apy' => round($weighted_apy, 4),
                'first_stake_at' => $stats['first_stake_at'],
                'last_stake_at' => $stats['last_stake_at'],
                'last_claim_at' => $stats['last_claim_at']
            ],
            'deposits_by_pool' => $deposits_by_pool,
            'active_deposits' => $active_deposits,
            'recent_rewards' => $recent_rewards,
            'available_pools' => $available_pools
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en get_staking_info.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
