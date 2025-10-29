<?php
/**
 * GET ADMIN ALERTS API
 * Obtiene alertas del sistema (solo admins)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/connection.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

try {
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    $severity = isset($_GET['severity']) ? $_GET['severity'] : null;
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 200) : 50;

    $query = "
        SELECT
            a.*,
            u.username as related_username
        FROM admin_alerts a
        LEFT JOIN users u ON a.related_user_id = u.user_id
        WHERE 1=1
    ";
    $params = [];

    if ($unread_only) {
        $query .= " AND a.is_read = FALSE";
    }

    if ($severity) {
        $query .= " AND a.severity = ?";
        $params[] = $severity;
    }

    $query .= " AND a.is_resolved = FALSE";
    $query .= " ORDER BY a.severity DESC, a.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar JSON data
    foreach ($alerts as &$alert) {
        if ($alert['data']) {
            $alert['data'] = json_decode($alert['data'], true);
        }
    }

    // Contar por severidad
    $stmt = $pdo->query("
        SELECT severity, COUNT(*) as count
        FROM admin_alerts
        WHERE is_resolved = FALSE
        GROUP BY severity
    ");
    $severity_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'success' => true,
        'alerts' => $alerts,
        'count' => count($alerts),
        'severity_counts' => $severity_counts,
        'unread_count' => intval($pdo->query("SELECT COUNT(*) FROM admin_alerts WHERE is_read = FALSE AND is_resolved = FALSE")->fetchColumn())
    ]);

} catch (PDOException $e) {
    error_log("Get alerts error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
