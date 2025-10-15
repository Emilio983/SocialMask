<?php
/**
 * Moderate Content
 * Take moderation action on reported content
 * Requires moderator role
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
    
    $moderatorId = $_SESSION['user_id'];
    
    // Check if user is moderator
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$moderatorId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !in_array($user['role'], ['moderator', 'admin'])) {
        throw new Exception('Moderator privileges required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['target_type']) || !isset($input['target_id']) || !isset($input['action_type'])) {
        throw new Exception('Missing required fields');
    }
    
    $targetType = $input['target_type'];
    $targetId = intval($input['target_id']);
    $actionType = $input['action_type'];
    $reason = trim($input['reason'] ?? '');
    $duration = isset($input['duration']) ? intval($input['duration']) : null;
    $reportId = isset($input['report_id']) ? intval($input['report_id']) : null;
    
    // Validate action type
    $validActions = ['warn', 'mute', 'ban', 'remove', 'restore', 'pin', 'feature'];
    if (!in_array($actionType, $validActions)) {
        throw new Exception('Invalid action type');
    }
    
    // Validate target type
    $validTypes = ['user', 'post', 'comment', 'message', 'community'];
    if (!in_array($targetType, $validTypes)) {
        throw new Exception('Invalid target type');
    }
    
    if (empty($reason)) {
        throw new Exception('Reason is required');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Calculate expiration
        $expiresAt = null;
        if ($duration && $duration > 0) {
            $expiresAt = date('Y-m-d H:i:s', time() + $duration);
        }
        
        // Create moderation action
        $stmt = $pdo->prepare('
            INSERT INTO moderation_actions (
                target_type,
                target_id,
                moderator_id,
                action_type,
                reason,
                duration,
                expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $targetType,
            $targetId,
            $moderatorId,
            $actionType,
            $reason,
            $duration,
            $expiresAt
        ]);
        
        $actionId = $pdo->lastInsertId();
        
        // Apply the action
        switch ($actionType) {
            case 'remove':
                switch ($targetType) {
                    case 'post':
                        $stmt = $pdo->prepare('UPDATE posts SET is_removed = TRUE, removed_reason = ? WHERE id = ?');
                        $stmt->execute([$reason, $targetId]);
                        break;
                    case 'comment':
                        $stmt = $pdo->prepare('UPDATE comments SET is_removed = TRUE WHERE id = ?');
                        $stmt->execute([$targetId]);
                        break;
                }
                break;
                
            case 'restore':
                switch ($targetType) {
                    case 'post':
                        $stmt = $pdo->prepare('UPDATE posts SET is_removed = FALSE, is_flagged = FALSE WHERE id = ?');
                        $stmt->execute([$targetId]);
                        break;
                    case 'comment':
                        $stmt = $pdo->prepare('UPDATE comments SET is_removed = FALSE WHERE id = ?');
                        $stmt->execute([$targetId]);
                        break;
                }
                break;
                
            case 'ban':
                if ($targetType === 'user') {
                    // Create user ban
                    $stmt = $pdo->prepare('
                        INSERT INTO user_bans (
                            user_id,
                            banned_by,
                            ban_type,
                            reason,
                            duration,
                            expires_at
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ');
                    
                    $banType = $duration ? 'temporary' : 'permanent';
                    $stmt->execute([
                        $targetId,
                        $moderatorId,
                        $banType,
                        $reason,
                        $duration,
                        $expiresAt
                    ]);
                }
                break;
                
            case 'mute':
                if ($targetType === 'user') {
                    // Mute user (prevent posting/commenting)
                    $stmt = $pdo->prepare('UPDATE users SET is_muted = TRUE, muted_until = ? WHERE id = ?');
                    $stmt->execute([$expiresAt, $targetId]);
                }
                break;
        }
        
        // Update related report if provided
        if ($reportId) {
            $stmt = $pdo->prepare('
                UPDATE content_reports
                SET status = "resolved",
                    action_taken = ?,
                    resolved_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$actionId, $reportId]);
        }
        
        // Notify user about action
        if ($targetType === 'user') {
            // TODO: Send notification to user
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'action_id' => $actionId,
            'message' => 'Moderation action applied successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
