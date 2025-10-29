<?php
/**
 * ============================================
 * GET PENDING MESSAGES
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
    
    // Get pending messages from queue
    $stmt = $conn->prepare("
        SELECT 
            em.id as message_id,
            em.sender_id,
            em.recipient_id,
            em.encrypted_content,
            em.message_type,
            em.content_type,
            em.session_id,
            em.ephemeral_timer,
            em.expires_at,
            em.reply_to,
            UNIX_TIMESTAMP(em.created_at) * 1000 as timestamp
        FROM message_queue mq
        JOIN encrypted_messages em ON mq.message_id = em.id
        WHERE mq.recipient_id = ?
            AND mq.status = 'pending'
            AND em.status != 'expired'
        ORDER BY em.created_at ASC
        LIMIT 50
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    $messageIds = [];
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
        $messageIds[] = $row['message_id'];
    }
    
    // Mark messages as delivered
    if (!empty($messageIds)) {
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $conn->prepare("
            UPDATE message_queue 
            SET status = 'delivered', delivered_at = NOW()
            WHERE message_id IN ($placeholders)
                AND recipient_id = ?
        ");
        
        $types = str_repeat('i', count($messageIds)) . 'i';
        $params = array_merge($messageIds, [$userId]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }
    
    sendJsonResponse([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
