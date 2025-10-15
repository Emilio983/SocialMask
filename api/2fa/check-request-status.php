<?php
/**
 * Check status of a login approval request
 */

require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');

if (!isset($_GET['request_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing request_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT status
        FROM login_approval_requests
        WHERE request_id = ?
        LIMIT 1
    ");

    $stmt->execute([(int)$_GET['request_id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'status' => $request['status']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check status',
        'message' => $e->getMessage()
    ]);
}
