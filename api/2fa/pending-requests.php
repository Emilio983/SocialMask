<?php
/**
 * Get pending login approval requests for authenticated user
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/TwoFactorAuth.php';

header('Content-Type: application/json');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $twoFA = new TwoFactorAuth($pdo);
    $requests = $twoFA->getPendingRequests($_SESSION['user_id']);

    echo json_encode([
        'success' => true,
        'requests' => $requests
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch requests',
        'message' => $e->getMessage()
    ]);
}
