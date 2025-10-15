<?php
/**
 * ============================================
 * DISTRIBUTE SENDER KEY TO MEMBER
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['group_id', 'recipient_id', 'encrypted_key', 'message_type'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $groupId = $input['group_id'];
    $recipientId = $input['recipient_id'];
    $senderId = $input['sender_id'] ?? 0;
    $encryptedKey = $input['encrypted_key'];
    $messageType = $input['message_type'];
    $senderKeyId = $input['sender_key_id'] ?? 'key_' . time();
    
    // Store encrypted sender key
    $stmt = $conn->prepare("
        INSERT INTO sender_keys (
            group_id, sender_id, recipient_id, 
            encrypted_key, message_type, sender_key_id
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        'siiiss',
        $groupId,
        $senderId,
        $recipientId,
        $encryptedKey,
        $messageType,
        $senderKeyId
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to distribute sender key");
    }
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Sender key distributed successfully'
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
