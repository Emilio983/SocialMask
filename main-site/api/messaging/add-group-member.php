<?php
/**
 * ============================================
 * ADD MEMBER TO GROUP
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['group_id']) || !isset($input['user_id']) || !isset($input['added_by'])) {
        throw new Exception("Missing required fields");
    }
    
    $groupId = $input['group_id'];
    $userId = $input['user_id'];
    $addedBy = $input['added_by'];
    
    // Check if adder is admin
    $stmt = $conn->prepare("
        SELECT is_admin FROM group_members
        WHERE group_id = ? AND user_id = ? AND is_active = TRUE
    ");
    $stmt->bind_param('si', $groupId, $addedBy);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result || !$result['is_admin']) {
        throw new Exception("Only admins can add members");
    }
    
    // Check if user is already a member
    $stmt = $conn->prepare("
        SELECT id FROM group_members
        WHERE group_id = ? AND user_id = ? AND is_active = TRUE
    ");
    $stmt->bind_param('si', $groupId, $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("User is already a member");
    }
    
    // Add member
    $stmt = $conn->prepare("
        INSERT INTO group_members (group_id, user_id, invited_by)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param('sii', $groupId, $userId, $addedBy);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to add member");
    }
    
    // Insert system message
    $systemMessage = "User $userId joined the group";
    $stmt = $conn->prepare("
        INSERT INTO group_messages (group_id, sender_id, encrypted_content, sender_key_id, iteration, iv, content_type)
        VALUES (?, 0, ?, '', 0, '', 'system')
    ");
    $stmt->bind_param('ss', $groupId, $systemMessage);
    $stmt->execute();
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Member added successfully'
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
