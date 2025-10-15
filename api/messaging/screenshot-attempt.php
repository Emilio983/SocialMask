<?php
/**
 * ============================================
 * LOG SCREENSHOT ATTEMPT
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id']) || !isset($input['contact_id'])) {
        throw new Exception("Missing required fields");
    }
    
    $userId = $input['user_id'];
    $contactId = $input['contact_id'];
    $timestamp = $input['timestamp'] ?? time() * 1000;
    
    // Log the attempt
    $stmt = $conn->prepare("
        INSERT INTO screenshot_attempts (
            user_id, contact_id, detected_at
        ) VALUES (?, ?, FROM_UNIXTIME(?))
    ");
    
    $timestampSeconds = floor($timestamp / 1000);
    $stmt->bind_param('iii', $userId, $contactId, $timestampSeconds);
    $stmt->execute();
    
    // Send notification to contact via Gun.js or push notification
    // (Implementation depends on notification system)
    
    sendJsonResponse([
        'success' => true,
        'logged' => true
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
