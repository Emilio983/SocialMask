<?php
/**
 * GET TRANSACTION LOGS API
 * Obtiene logs detallados de transacciones (solo admins)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/connection.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

try {
    // Parámetros de filtro
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 500) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $action = isset($_GET['action']) ? $_GET['action'] : null;
    $transaction_type = isset($_GET['type']) ? $_GET['type'] : null;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    // Query base
    $query = "
        SELECT
            pl.*,
            u.username,
            u.wallet_address as user_wallet
        FROM payment_logs pl
        LEFT JOIN users u ON pl.user_id = u.user_id
        WHERE 1=1
    ";
    $params = [];

    if ($action) {
        $query .= " AND pl.action = ?";
        $params[] = $action;
    }

    if ($transaction_type) {
        $query .= " AND pl.transaction_type = ?";
        $params[] = $transaction_type;
    }

    if ($user_id) {
        $query .= " AND pl.user_id = ?";
        $params[] = $user_id;
    }

    if ($start_date) {
        $query .= " AND pl.created_at >= ?";
        $params[] = $start_date;
    }

    if ($end_date) {
        $query .= " AND pl.created_at <= ?";
        $params[] = $end_date;
    }

    // Contar total
    $count_query = "SELECT COUNT(*) as total FROM ($query) as subq";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Agregar ordenamiento y paginación
    $query .= " ORDER BY pl.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar logs
    foreach ($logs as &$log) {
        if ($log['request_data']) {
            $log['request_data'] = json_decode($log['request_data'], true);
        }
        if ($log['response_data']) {
            $log['response_data'] = json_decode($log['response_data'], true);
        }
        if ($log['blockchain_data']) {
            $log['blockchain_data'] = json_decode($log['blockchain_data'], true);
        }
    }

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total' => intval($total),
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => ($offset + $limit) < $total
    ]);

} catch (PDOException $e) {
    error_log("Get transaction logs error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
