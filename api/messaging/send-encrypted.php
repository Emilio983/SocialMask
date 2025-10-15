<?php
/**
 * ============================================
 * SEND ENCRYPTED MESSAGE
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['sender_id', 'recipient_id', 'encrypted_content', 'message_type'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate users exist
    $stmt = $conn->prepare("SELECT id FROM users WHERE id IN (?, ?)");
    $stmt->bind_param('ii', $input['sender_id'], $input['recipient_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 2) {
        throw new Exception("Invalid sender or recipient");
    }
    
    // Insert encrypted message
    $stmt = $conn->prepare("
        INSERT INTO encrypted_messages (
            sender_id, recipient_id, encrypted_content, content_type,
            session_id, pre_key_id, message_type, ephemeral_timer,
            expires_at, reply_to, status, sent_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', NOW())
    ");
    
    $contentType = $input['content_type'] ?? 'text';
    $sessionId = $input['session_id'] ?? null;
    $preKeyId = $input['pre_key_id'] ?? null;
    $ephemeralTimer = $input['ephemeral_timer'] ?? 0;
    $expiresAt = $input['expires_at'] ?? null;
    $replyTo = $input['reply_to'] ?? null;
    
    $stmt->bind_param(
        'iisssiiisi',
        $input['sender_id'],
        $input['recipient_id'],
        $input['encrypted_content'],
        $contentType,
        $sessionId,
        $preKeyId,
        $input['message_type'],
        $ephemeralTimer,
        $expiresAt,
        $replyTo
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to save message");
    }
    
    $messageId = $conn->insert_id;
    
    // Add to message queue for offline delivery
    $stmt = $conn->prepare("
        INSERT INTO message_queue (message_id, recipient_id, status)
        VALUES (?, ?, 'pending')
    ");
    $stmt->bind_param('ii', $messageId, $input['recipient_id']);
    $stmt->execute();
    
    // Return success
    sendJsonResponse([
        'success' => true,
        'message_id' => $messageId,
        'sent_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
