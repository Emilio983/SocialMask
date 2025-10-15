<?php
/**
 * API: Obtener Ganancias del Creador
 * Endpoint: GET /api/paywall/get_earnings.php
 * 
 * Obtiene estadísticas de ganancias de un creador
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/response.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Autenticación requerida
$user = authenticate();
if (!$user) {
    sendError('Unauthorized', 401);
}

// Rango de fechas (opcional)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

try {
    $db = getConnection();
    
    // Estadísticas generales del creador
    $stmt = $db->prepare("
        CALL GetCreatorStats(?)
    ");
    $stmt->execute([$user['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    
    // Ganancias detalladas por contenido
    $where = ["pc.user_id = ?"];
    $params = [$user['id']];
    
    if ($start_date) {
        $where[] = "pe.earned_at >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $where[] = "pe.earned_at <= ?";
        $params[] = $end_date;
    }
    
    $where_clause = implode(' AND ', $where);
    
    $stmt = $db->prepare("
        SELECT 
            pc.id as content_id,
            pc.contract_content_id,
            pc.title,
            pc.price,
            COUNT(pe.id) as total_earnings,
            SUM(pe.amount) as total_amount,
            SUM(pe.fee) as total_fees,
            SUM(pe.net_amount) as total_net,
            MIN(pe.earned_at) as first_earning,
            MAX(pe.earned_at) as last_earning
        FROM paywall_content pc
        LEFT JOIN paywall_earnings pe ON pc.id = pe.content_id
        WHERE $where_clause
        GROUP BY pc.id
        ORDER BY total_net DESC
    ");
    $stmt->execute($params);
    $earnings_by_content = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Historial de earnings recientes
    $stmt = $db->prepare("
        SELECT 
            pe.*,
            pc.title as content_title,
            pp.tx_hash,
            u.username as buyer_username
        FROM paywall_earnings pe
        JOIN paywall_content pc ON pe.content_id = pc.id
        JOIN paywall_purchases pp ON pe.purchase_id = pp.id
        JOIN users u ON pp.user_id = u.id
        WHERE pe.user_id = ?
        ORDER BY pe.earned_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    $recent_earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Earnings por mes (últimos 12 meses)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(pe.earned_at, '%Y-%m') as month,
            COUNT(pe.id) as count,
            SUM(pe.amount) as total_amount,
            SUM(pe.net_amount) as total_net
        FROM paywall_earnings pe
        JOIN paywall_content pc ON pe.content_id = pc.id
        WHERE pc.user_id = ?
        AND pe.earned_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month DESC
    ");
    $stmt->execute([$user['id']]);
    $monthly_earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Balance pendiente de retiro (on-chain)
    // Nota: Esto debe verificarse con el smart contract
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(net_amount), 0) as total_earned
        FROM paywall_earnings
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $total_earned = $stmt->fetch(PDO::FETCH_ASSOC)['total_earned'];
    
    sendSuccess([
        'stats' => [
            'total_content' => (int)$stats['total_content'],
            'total_sales' => (int)$stats['total_sales'],
            'total_revenue' => $stats['total_revenue'],
            'net_earnings' => $stats['net_earnings'],
            'unique_buyers' => (int)$stats['unique_buyers'],
            'avg_conversion_rate' => (float)$stats['avg_conversion_rate'],
            'total_earned' => $total_earned
        ],
        'earnings_by_content' => $earnings_by_content,
        'recent_earnings' => $recent_earnings,
        'monthly_earnings' => $monthly_earnings
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_earnings: " . $e->getMessage());
    sendError('Database error', 500);
} catch (Exception $e) {
    error_log("Error in get_earnings: " . $e->getMessage());
    sendError('Server error', 500);
}
