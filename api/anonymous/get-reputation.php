<?php
/**
 * Get Anonymous Reputation
 * Retrieve reputation data for anonymous identity
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['nullifier'])) {
        throw new Exception('Nullifier required');
    }
    
    $nullifier = $input['nullifier'];
    
    // Get reputation
    $stmt = $pdo->prepare('
        SELECT 
            reputation_score,
            post_count,
            comment_count,
            upvotes_received,
            downvotes_received,
            badges,
            is_verified,
            verified_at,
            DATEDIFF(NOW(), first_seen_at) as days_active
        FROM anonymous_reputation
        WHERE nullifier = ?
    ');
    
    $stmt->execute([$nullifier]);
    $reputation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reputation) {
        $reputation['badges'] = json_decode($reputation['badges'] ?? '[]', true);
        
        echo json_encode([
            'success' => true,
            'reputation_score' => $reputation['reputation_score'],
            'post_count' => $reputation['post_count'],
            'comment_count' => $reputation['comment_count'],
            'upvotes_received' => $reputation['upvotes_received'],
            'downvotes_received' => $reputation['downvotes_received'],
            'badges' => $reputation['badges'],
            'verified' => (bool)$reputation['is_verified'],
            'verified_at' => $reputation['verified_at'],
            'days_active' => $reputation['days_active']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'reputation_score' => 0,
            'post_count' => 0,
            'comment_count' => 0,
            'verified' => false,
            'badges' => []
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
