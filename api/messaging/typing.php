<?php
/**
 * ============================================
 * TYPING INDICATOR
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id']) || !isset($input['recipient_id'])) {
        throw new Exception("Missing required fields");
    }
    
    $userId = $input['user_id'];
    $recipientId = $input['recipient_id'];
    $isTyping = $input['is_typing'] ?? true;
    
    if ($isTyping) {
        // Insert or update typing indicator
        $stmt = $conn->prepare("
            INSERT INTO typing_indicators (user_id, conversation_with, is_typing)
            VALUES (?, ?, TRUE)
            ON DUPLICATE KEY UPDATE
                is_typing = TRUE,
                expires_at = TIMESTAMPADD(SECOND, 5, NOW()),
                updated_at = NOW()
        ");
        
        $stmt->bind_param('ii', $userId, $recipientId);
        $stmt->execute();
    } else {
        // Remove typing indicator
        $stmt = $conn->prepare("
            DELETE FROM typing_indicators
            WHERE user_id = ? AND conversation_with = ?
        ");
        
        $stmt->bind_param('ii', $userId, $recipientId);
        $stmt->execute();
    }
    
    sendJsonResponse([
        'success' => true
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
