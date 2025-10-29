<?php
/**
 * ============================================
 * MARK MESSAGE AS READ
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['message_id']) || !isset($input['user_id'])) {
        throw new Exception("Missing required fields");
    }
    
    $messageId = $input['message_id'];
    $userId = $input['user_id'];
    
    // Update message status
    $stmt = $conn->prepare("
        UPDATE encrypted_messages
        SET status = 'read', read_at = NOW()
        WHERE id = ?
            AND recipient_id = ?
            AND status != 'read'
    ");
    
    $stmt->bind_param('ii', $messageId, $userId);
    $stmt->execute();
    
    $affected = $stmt->affected_rows;
    
    sendJsonResponse([
        'success' => true,
        'updated' => $affected > 0
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
