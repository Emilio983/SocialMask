<?php
/**
 * Reject a login request from an authorized device
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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['request_id']) || !isset($input['device_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $twoFA = new TwoFactorAuth($pdo);
    $result = $twoFA->rejectLoginRequest(
        (int)$input['request_id'],
        (int)$input['device_id']
    );

    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to reject login',
        'message' => $e->getMessage()
    ]);
}
