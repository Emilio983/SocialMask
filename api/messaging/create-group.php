<?php
/**
 * ============================================
 * CREATE ENCRYPTED GROUP
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['group_id', 'name', 'creator_id', 'member_ids'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $groupId = $input['group_id'];
    $name = $input['name'];
    $creatorId = $input['creator_id'];
    $memberIds = $input['member_ids'];
    $description = $input['description'] ?? '';
    $senderKeyId = $input['sender_key_id'] ?? 'key_' . time();
    
    // Validate creator exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param('i', $creatorId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception("Invalid creator_id");
    }
    
    // Create admin IDs array (creator is admin by default)
    $adminIds = json_encode([$creatorId]);
    
    // Insert group
    $stmt = $conn->prepare("
        INSERT INTO encrypted_groups (
            group_id, name, description, creator_id, 
            admin_ids, sender_key_id
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        'sssiss',
        $groupId,
        $name,
        $description,
        $creatorId,
        $adminIds,
        $senderKeyId
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create group");
    }
    
    // Add members
    $stmt = $conn->prepare("
        INSERT INTO group_members (group_id, user_id, is_admin, invited_by)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($memberIds as $memberId) {
        $isAdmin = ($memberId == $creatorId);
        $stmt->bind_param('siii', $groupId, $memberId, $isAdmin, $creatorId);
        $stmt->execute();
    }
    
    sendJsonResponse([
        'success' => true,
        'group_id' => $groupId,
        'message' => 'Group created successfully'
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
