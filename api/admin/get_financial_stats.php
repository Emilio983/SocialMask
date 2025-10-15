<?php
/**
 * GET FINANCIAL STATS API
 * Obtiene estadísticas financieras (solo admins)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/connection.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

try {
    $period = isset($_GET['period']) ? $_GET['period'] : 'today';
    $start_date = null;
    $end_date = date('Y-m-d');

    switch ($period) {
        case 'today':
            $start_date = date('Y-m-d');
            break;
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'year':
            $start_date = date('Y-m-d', strtotime('-365 days'));
            break;
        default:
            $start_date = date('Y-m-d');
    }

    // Obtener stats del período
    $stmt = $pdo->prepare("
        SELECT
            SUM(total_transactions) as total_transactions,
            SUM(total_volume_sphe) as total_volume,
            SUM(membership_revenue) as membership_revenue,
            SUM(survey_revenue) as survey_revenue,
            SUM(platform_fees) as platform_fees,
            SUM(rewards_distributed) as rewards_distributed,
            SUM(refunds_processed) as refunds_processed,
            SUM(failed_transactions) as failed_transactions,
            SUM(new_memberships) as new_memberships,
            AVG(active_surveys) as avg_active_surveys
        FROM financial_stats
        WHERE stat_date >= ? AND stat_date <= ?
    ");

    $stmt->execute([$start_date, $end_date]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Stats en tiempo real (hoy)
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as transactions_today,
            COALESCE(SUM(amount), 0) as volume_today
        FROM sphe_transactions
        WHERE DATE(created_at) = CURDATE()
        AND status = 'completed'
    ");
    $today = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pending refunds
    $pending_refunds = $pdo->query("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM refunds
        WHERE status = 'pending'
    ")->fetch(PDO::FETCH_ASSOC);

    // Failed transactions últimas 24h
    $failed_24h = $pdo->query("
        SELECT COUNT(*) as count
        FROM payment_logs
        WHERE action = 'fail'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ")->fetchColumn();

    // Top usuarios por volumen
    $stmt = $pdo->query("
        SELECT
            u.user_id,
            u.username,
            u.wallet_address,
            COALESCE(SUM(st.amount), 0) as total_volume
        FROM users u
        LEFT JOIN sphe_transactions st ON (u.user_id = st.from_user_id OR u.user_id = st.to_user_id)
        WHERE st.status = 'completed'
        AND DATE(st.created_at) >= CURDATE() - INTERVAL 30 DAY
        GROUP BY u.user_id
        ORDER BY total_volume DESC
        LIMIT 10
    ");
    $top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Gráfico de volumen diario (últimos 30 días)
    $stmt = $pdo->query("
        SELECT
            stat_date,
            total_volume_sphe as volume,
            total_transactions as transactions
        FROM financial_stats
        WHERE stat_date >= CURDATE() - INTERVAL 30 DAY
        ORDER BY stat_date ASC
    ");
    $daily_chart = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'period' => $period,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'stats' => [
            'total_transactions' => intval($stats['total_transactions'] ?? 0),
            'total_volume' => floatval($stats['total_volume'] ?? 0),
            'membership_revenue' => floatval($stats['membership_revenue'] ?? 0),
            'survey_revenue' => floatval($stats['survey_revenue'] ?? 0),
            'platform_fees' => floatval($stats['platform_fees'] ?? 0),
            'rewards_distributed' => floatval($stats['rewards_distributed'] ?? 0),
            'refunds_processed' => floatval($stats['refunds_processed'] ?? 0),
            'failed_transactions' => intval($stats['failed_transactions'] ?? 0),
            'new_memberships' => intval($stats['new_memberships'] ?? 0),
            'avg_active_surveys' => floatval($stats['avg_active_surveys'] ?? 0)
        ],
        'real_time' => [
            'transactions_today' => intval($today['transactions_today']),
            'volume_today' => floatval($today['volume_today']),
            'pending_refunds_count' => intval($pending_refunds['count']),
            'pending_refunds_total' => floatval($pending_refunds['total']),
            'failed_24h' => intval($failed_24h)
        ],
        'top_users' => $top_users,
        'daily_chart' => $daily_chart
    ]);

} catch (PDOException $e) {
    error_log("Get financial stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
