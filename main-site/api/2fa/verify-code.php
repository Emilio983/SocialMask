<?php
/**
 * Verify 2FA code during login process
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/TwoFactorAuth.php';

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['code'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $twoFA = new TwoFactorAuth($pdo);
    $result = $twoFA->verifyCode((int)$input['user_id'], $input['code']);

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
        'error' => 'Verification failed',
        'message' => $e->getMessage()
    ]);
}
