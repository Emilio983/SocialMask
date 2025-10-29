<?php
/**
 * ============================================
 * CHECK IF USER HAS PRE-KEYS
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        throw new Exception("Missing user_id");
    }
    
    $userId = $input['user_id'];
    
    // Check if pre-keys exist
    $stmt = $conn->prepare("
        SELECT 
            one_time_prekeys,
            keys_generated_at
        FROM user_prekeys
        WHERE user_id = ?
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJsonResponse([
            'success' => true,
            'exists' => false,
            'keys_low' => false
        ]);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $preKeys = json_decode($row['one_time_prekeys'], true);
    $keysCount = count($preKeys);
    
    // Warn if less than 20 keys remaining
    $keysLow = $keysCount < 20;
    
    sendJsonResponse([
        'success' => true,
        'exists' => true,
        'keys_low' => $keysLow,
        'keys_remaining' => $keysCount,
        'generated_at' => $row['keys_generated_at']
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
