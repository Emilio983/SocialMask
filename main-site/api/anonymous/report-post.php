<?php
/**
 * Report Anonymous Post
 * Submit moderation report for anonymous post
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id']) || !isset($input['reason'])) {
        throw new Exception('Missing required fields');
    }
    
    $postId = intval($input['post_id']);
    $reason = $input['reason'];
    $description = $input['description'] ?? null;
    $reporterNullifier = $input['reporter_nullifier'] ?? 'anonymous';
    
    $validReasons = ['spam', 'harassment', 'illegal', 'misinformation', 'other'];
    if (!in_array($reason, $validReasons)) {
        throw new Exception('Invalid reason');
    }
    
    // Check post exists
    $stmt = $pdo->prepare('SELECT id FROM anonymous_posts WHERE id = ?');
    $stmt->execute([$postId]);
    if (!$stmt->fetch()) {
        throw new Exception('Post not found');
    }
    
    // Check if already reported by this nullifier
    if ($reporterNullifier !== 'anonymous') {
        $stmt = $pdo->prepare('SELECT id FROM anonymous_reports WHERE post_id = ? AND reporter_nullifier = ?');
        $stmt->execute([$postId, $reporterNullifier]);
        if ($stmt->fetch()) {
            throw new Exception('Already reported');
        }
    }
    
    $pdo->beginTransaction();
    
    try {
        // Create report
        $stmt = $pdo->prepare('
            INSERT INTO anonymous_reports (post_id, reporter_nullifier, reason, description)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$postId, $reporterNullifier, $reason, $description]);
        
        // Update post flag count
        $stmt = $pdo->prepare('
            UPDATE anonymous_posts 
            SET flag_count = flag_count + 1,
                is_flagged = IF(flag_count >= 3, TRUE, is_flagged)
            WHERE id = ?
        ');
        $stmt->execute([$postId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
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
