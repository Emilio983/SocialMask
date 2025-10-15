<?php
/**
 * ============================================
 * SEND ENCRYPTED GROUP MESSAGE
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['group_id', 'sender_id', 'encrypted_content', 'sender_key_id', 'iteration', 'iv'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $groupId = $input['group_id'];
    $senderId = $input['sender_id'];
    
    // Verify user is member of group
    $stmt = $conn->prepare("
        SELECT id FROM group_members
        WHERE group_id = ? AND user_id = ? AND is_active = TRUE
    ");
    $stmt->bind_param('si', $groupId, $senderId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception("User is not a member of this group");
    }
    
    // Check if only admins can post
    $stmt = $conn->prepare("
        SELECT only_admins_post FROM encrypted_groups WHERE group_id = ?
    ");
    $stmt->bind_param('s', $groupId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['only_admins_post']) {
        // Check if sender is admin
        $stmt = $conn->prepare("
            SELECT is_admin FROM group_members
            WHERE group_id = ? AND user_id = ?
        ");
        $stmt->bind_param('si', $groupId, $senderId);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        
        if (!$member['is_admin']) {
            throw new Exception("Only admins can post in this group");
        }
    }
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO group_messages (
            group_id, sender_id, encrypted_content, sender_key_id,
            iteration, iv, content_type, ephemeral_timer, expires_at, reply_to
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $contentType = $input['content_type'] ?? 'text';
    $ephemeralTimer = $input['ephemeral_timer'] ?? 0;
    $expiresAt = $input['expires_at'] ?? null;
    $replyTo = $input['reply_to'] ?? null;
    
    $stmt->bind_param(
        'sisbsissi',
        $groupId,
        $senderId,
        $input['encrypted_content'],
        $input['sender_key_id'],
        $input['iteration'],
        $input['iv'],
        $contentType,
        $ephemeralTimer,
        $expiresAt,
        $replyTo
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to send message");
    }
    
    $messageId = $conn->insert_id;
    
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
