<?php
/**
 * Block User
 * Block another user (peer-to-peer blocking)
 */

require_once '../../config/config.php';
require_once '../utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $blockerId = $_SESSION['user_id'];
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        throw new Exception('User ID required');
    }
    
    $blockedId = intval($input['user_id']);
    $reason = trim($input['reason'] ?? '');
    $blockType = $input['block_type'] ?? 'full';
    
    // Validate block type
    if (!in_array($blockType, ['full', 'mute', 'hide'])) {
        $blockType = 'full';
    }
    
    // Can't block yourself
    if ($blockerId === $blockedId) {
        throw new Exception('Cannot block yourself');
    }
    
    // Check if user exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$blockedId]);
    if (!$stmt->fetch()) {
        throw new Exception('User not found');
    }
    
    // Check if already blocked
    $stmt = $pdo->prepare('SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $stmt->execute([$blockerId, $blockedId]);
    if ($stmt->fetch()) {
        throw new Exception('User already blocked');
    }
    
    // Create block
    $stmt = $pdo->prepare('
        INSERT INTO user_blocks (blocker_id, blocked_id, reason, block_type)
        VALUES (?, ?, ?, ?)
    ');
    
    $stmt->execute([$blockerId, $blockedId, $reason, $blockType]);
    
    echo json_encode([
        'success' => true,
        'message' => 'User blocked successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
