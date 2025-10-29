<?php
/**
 * Report Content
 * Submit a report for moderation review
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Get reporter info
    $reporterId = $_SESSION['user_id'] ?? null; // Can be anonymous
    $reporterIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Validate required fields
    if (!isset($input['target_type']) || !isset($input['target_id']) || !isset($input['category'])) {
        throw new Exception('Missing required fields');
    }
    
    $targetType = $input['target_type'];
    $targetId = intval($input['target_id']);
    $category = $input['category'];
    $description = trim($input['description'] ?? '');
    $evidence = $input['evidence'] ?? [];
    
    // Validate target type
    $validTypes = ['user', 'post', 'comment', 'message', 'community', 'media'];
    if (!in_array($targetType, $validTypes)) {
        throw new Exception('Invalid target type');
    }
    
    // Validate category
    $validCategories = ['spam', 'harassment', 'hate_speech', 'violence', 'sexual', 'illegal', 'copyright', 'misinformation', 'self_harm', 'other'];
    if (!in_array($category, $validCategories)) {
        throw new Exception('Invalid category');
    }
    
    // Check if target exists (basic validation)
    switch ($targetType) {
        case 'user':
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            break;
        case 'post':
            $stmt = $pdo->prepare('SELECT id FROM posts WHERE id = ?');
            break;
        case 'comment':
            $stmt = $pdo->prepare('SELECT id FROM comments WHERE id = ?');
            break;
        default:
            $stmt = null;
    }
    
    if ($stmt) {
        $stmt->execute([$targetId]);
        if (!$stmt->fetch()) {
            throw new Exception('Target not found');
        }
    }
    
    // Rate limiting check (max 10 reports per user per hour)
    if ($reporterId) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM content_reports 
            WHERE reporter_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ');
        $stmt->execute([$reporterId]);
        $recentReports = $stmt->fetchColumn();
        
        if ($recentReports >= 10) {
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
    }
    
    // Check for duplicate reports
    $stmt = $pdo->prepare('
        SELECT id FROM content_reports
        WHERE reporter_id = ?
        AND target_type = ?
        AND target_id = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ');
    $stmt->execute([$reporterId, $targetType, $targetId]);
    if ($stmt->fetch()) {
        throw new Exception('You have already reported this content');
    }
    
    // Calculate priority
    $priority = 1; // Default: low
    if (in_array($category, ['illegal', 'self_harm', 'violence'])) {
        $priority = 4; // Urgent
    } elseif (in_array($category, ['harassment', 'hate_speech'])) {
        $priority = 3; // High
    } elseif (in_array($category, ['sexual', 'spam'])) {
        $priority = 2; // Medium
    }
    
    $pdo->beginTransaction();
    
    try {
        // Create report
        $stmt = $pdo->prepare('
            INSERT INTO content_reports (
                reporter_id,
                reporter_ip,
                target_type,
                target_id,
                category,
                description,
                evidence,
                priority,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $reporterId,
            $reporterIp,
            $targetType,
            $targetId,
            $category,
            $description,
            json_encode($evidence),
            $priority,
            'pending'
        ]);
        
        $reportId = $pdo->lastInsertId();
        
        // Auto-flag content if multiple reports
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM content_reports
            WHERE target_type = ?
            AND target_id = ?
            AND status != "dismissed"
        ');
        $stmt->execute([$targetType, $targetId]);
        $reportCount = $stmt->fetchColumn();
        
        if ($reportCount >= 3) {
            // Auto-flag content
            switch ($targetType) {
                case 'post':
                    $stmt = $pdo->prepare('UPDATE posts SET is_flagged = TRUE WHERE id = ?');
                    $stmt->execute([$targetId]);
                    break;
                case 'comment':
                    $stmt = $pdo->prepare('UPDATE comments SET is_flagged = TRUE WHERE id = ?');
                    $stmt->execute([$targetId]);
                    break;
            }
        }
        
        // Notify moderators for urgent reports
        if ($priority >= 3) {
            // TODO: Send notification to moderators
            // Could use WebSocket, email, or push notification
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'report_id' => $reportId,
            'priority' => $priority,
            'message' => 'Report submitted successfully'
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
