<?php
/**
 * GET REFUNDS API
 * Obtiene lista de reembolsos (solo admins)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/connection.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

try {
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 200) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $query = "
        SELECT
            r.*,
            u.username,
            u.wallet_address,
            req_by.username as requested_by_username,
            app_by.username as approved_by_username
        FROM refunds r
        LEFT JOIN users u ON r.user_id = u.user_id
        LEFT JOIN users req_by ON r.requested_by = req_by.user_id
        LEFT JOIN users app_by ON r.approved_by = app_by.user_id
        WHERE 1=1
    ";
    $params = [];

    if ($status) {
        $query .= " AND r.status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY r.requested_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'refunds' => $refunds,
        'count' => count($refunds)
    ]);

} catch (PDOException $e) {
    error_log("Get refunds error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
