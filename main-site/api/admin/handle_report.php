<?php
/**
 * ADMIN API: HANDLE REPORT
 * Update report status (reviewing, resolved, dismissed)
 */

require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');
session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

$report_id = $data['report_id'] ?? null;
$new_status = $data['status'] ?? null;
$admin_notes = $data['admin_notes'] ?? null;

if (!$report_id || !$new_status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$valid_statuses = ['pending', 'reviewing', 'resolved', 'dismissed'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    // Get report
    $stmt = $pdo->prepare("SELECT * FROM user_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Report not found']);
        exit;
    }

    // Update report
    $resolved_at = in_array($new_status, ['resolved', 'dismissed']) ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("
        UPDATE user_reports
        SET
            status = ?,
            reviewed_by = ?,
            admin_notes = ?,
            resolved_at = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $new_status,
        $admin_id,
        $admin_notes,
        $resolved_at,
        $report_id
    ]);

    // If resolved, take action on reported content/user if needed
    if ($new_status === 'resolved') {
        // Log this as a strike against the reported user
        if ($report['reported_user_id']) {
            // You could implement a strike system here
            // For now, just log it
            error_log("Report #{$report_id} resolved against user {$report['reported_user_id']}");
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Report updated successfully',
        'new_status' => $new_status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update report'
    ]);
}
?>
