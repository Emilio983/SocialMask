<?php
/**
 * RESOLVE ALERT API
 * Marca una alerta como resuelta (solo admins)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../../config/connection.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$alert_id = isset($input['alert_id']) ? intval($input['alert_id']) : null;
$resolution_notes = isset($input['notes']) ? trim($input['notes']) : '';

if (!$alert_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'alert_id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE admin_alerts
        SET is_resolved = TRUE,
            is_read = TRUE,
            resolved_by = ?,
            resolved_at = NOW(),
            resolution_notes = ?
        WHERE id = ?
    ");

    $result = $stmt->execute([$admin_id, $resolution_notes, $alert_id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Alert not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Alert resolved successfully',
        'alert_id' => $alert_id
    ]);

} catch (PDOException $e) {
    error_log("Resolve alert error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
