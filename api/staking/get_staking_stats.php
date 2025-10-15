<?php
/**
 * API: Obtener estadísticas globales de staking
 * Endpoint: /api/staking/get_staking_stats.php
 * Método: GET
 * Descripción: Retorna estadísticas globales del sistema de staking
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

    // Parámetro opcional para user_id
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    // ============================================
    // ESTADÍSTICAS GLOBALES
    // ============================================

    // Total Value Locked (TVL) en todos los pools
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total_locked
        FROM staking_deposits
        WHERE status = 'active'
    ");
    $stmt->execute();
    $tvl = $stmt->fetch(PDO::FETCH_ASSOC)['total_locked'];

    // Total de participantes únicos
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as total_participants
        FROM staking_deposits
        WHERE status = 'active'
    ");
    $stmt->execute();
    $total_participants = $stmt->fetch(PDO::FETCH_ASSOC)['total_participants'];

    // Total de rewards distribuidas
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_rewards
        FROM staking_rewards
    ");
    $stmt->execute();
    $total_rewards = $stmt->fetch(PDO::FETCH_ASSOC)['total_rewards'];

    // Estadísticas por pool
    $stmt = $pdo->prepare("
        SELECT 
            p.pool_id,
            p.name,
            p.lock_period_days,
            p.reward_multiplier,
            p.min_stake,
            p.apy,
            p.total_staked,
            p.participants_count,
            p.is_active,
            CASE 
                WHEN :tvl > 0 THEN ROUND((p.total_staked / :tvl * 100), 2)
                ELSE 0
            END as pool_percentage
        FROM staking_pools_info p
        ORDER BY p.pool_id
    ");
    $stmt->execute(['tvl' => $tvl]);
    $pools_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 stakers
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.wallet_address,
            ss.current_staked,
            ss.total_rewards_claimed,
            ss.active_deposits_count,
            RANK() OVER (ORDER BY ss.current_staked DESC) as ranking
        FROM users u
        INNER JOIN staking_stats ss ON u.id = ss.user_id
        WHERE ss.current_staked > 0
        ORDER BY ss.current_staked DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_stakers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Actividad reciente (últimos 7 días)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as transactions_count,
            SUM(CASE WHEN transaction_type = 'stake' THEN amount ELSE 0 END) as stakes,
            SUM(CASE WHEN transaction_type = 'unstake' THEN amount ELSE 0 END) as unstakes,
            SUM(CASE WHEN transaction_type = 'claim' THEN amount ELSE 0 END) as claims
        FROM staking_transactions_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND status = 'confirmed'
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute();
    $activity_7days = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crecimiento de TVL (últimos 30 días)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(staked_at) as date,
            SUM(amount) as daily_stakes
        FROM staking_deposits
        WHERE staked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(staked_at)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $tvl_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Distribución de rewards por mes
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(claimed_at, '%Y-%m') as month,
            COUNT(*) as claims_count,
            SUM(amount) as total_rewards
        FROM staking_rewards
        WHERE claimed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(claimed_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $rewards_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // ESTADÍSTICAS DEL USUARIO (si se proporciona)
    // ============================================
    $user_stats = null;
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT 
                ss.*,
                CASE 
                    WHEN :total_participants > 0 THEN
                        (SELECT COUNT(*) FROM staking_stats WHERE current_staked > ss.current_staked) + 1
                    ELSE NULL
                END as user_ranking,
                CASE 
                    WHEN :tvl > 0 THEN ROUND((ss.current_staked / :tvl * 100), 4)
                    ELSE 0
                END as user_tvl_percentage
            FROM staking_stats ss
            WHERE ss.user_id = ?
        ");
        $stmt->execute([
            'total_participants' => $total_participants,
            'tvl' => $tvl,
            $user_id
        ]);
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // APY promedio ponderado del sistema
    $weighted_system_apy = 0;
    if ($tvl > 0) {
        foreach ($pools_stats as $pool) {
            if ($pool['total_staked'] > 0) {
                $weight = $pool['total_staked'] / $tvl;
                $weighted_system_apy += $pool['apy'] * $weight;
            }
        }
    }

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'global' => [
                'total_value_locked' => $tvl,
                'total_participants' => (int)$total_participants,
                'total_rewards_distributed' => $total_rewards,
                'weighted_system_apy' => round($weighted_system_apy, 4),
                'active_pools_count' => count(array_filter($pools_stats, fn($p) => $p['is_active']))
            ],
            'pools' => $pools_stats,
            'top_stakers' => $top_stakers,
            'activity_7days' => $activity_7days,
            'tvl_growth_30days' => $tvl_growth,
            'rewards_by_month' => $rewards_by_month,
            'user_stats' => $user_stats
        ],
        'metadata' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'cache_duration' => 300 // 5 minutos
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en get_staking_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
