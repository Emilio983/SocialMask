<?php
/**
 * ============================================
 * REMOVE MEMBER FROM GROUP
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['group_id']) || !isset($input['user_id']) || !isset($input['removed_by'])) {
        throw new Exception("Missing required fields");
    }
    
    $groupId = $input['group_id'];
    $userId = $input['user_id'];
    $removedBy = $input['removed_by'];
    
    // Check if remover is admin
    $stmt = $conn->prepare("
        SELECT is_admin FROM group_members
        WHERE group_id = ? AND user_id = ? AND is_active = TRUE
    ");
    $stmt->bind_param('si', $groupId, $removedBy);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result || !$result['is_admin']) {
        throw new Exception("Only admins can remove members");
    }
    
    // Check if trying to remove creator
    $stmt = $conn->prepare("
        SELECT creator_id FROM encrypted_groups WHERE group_id = ?
    ");
    $stmt->bind_param('s', $groupId);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_assoc();
    
    if ($group['creator_id'] == $userId) {
        throw new Exception("Cannot remove group creator");
    }
    
    // Remove member (soft delete)
    $stmt = $conn->prepare("
        UPDATE group_members
        SET is_active = FALSE, left_at = NOW()
        WHERE group_id = ? AND user_id = ?
    ");
    $stmt->bind_param('si', $groupId, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to remove member");
    }
    
    // Insert system message
    $systemMessage = "User $userId left the group";
    $stmt = $conn->prepare("
        INSERT INTO group_messages (group_id, sender_id, encrypted_content, sender_key_id, iteration, iv, content_type)
        VALUES (?, 0, ?, '', 0, '', 'system')
    ");
    $stmt->bind_param('ss', $groupId, $systemMessage);
    $stmt->execute();
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Member removed successfully'
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
