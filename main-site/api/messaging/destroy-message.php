<?php
/**
 * ============================================
 * DESTROY EPHEMERAL MESSAGE
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['message_id'])) {
        throw new Exception("Missing message_id");
    }
    
    $messageId = $input['message_id'];
    
    // Delete encrypted content and mark as expired
    $stmt = $conn->prepare("
        UPDATE encrypted_messages
        SET encrypted_content = NULL,
            status = 'expired',
            deleted = TRUE
        WHERE id = ?
            AND (ephemeral_timer > 0 OR expires_at IS NOT NULL)
    ");
    
    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    
    $affected = $stmt->affected_rows;
    
    sendJsonResponse([
        'success' => true,
        'destroyed' => $affected > 0
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
