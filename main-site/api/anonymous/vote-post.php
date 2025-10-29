<?php
/**
 * Vote on Anonymous Post
 * Allows verified users to vote on anonymous posts
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id']) || !isset($input['vote_type']) || !isset($input['voter_nullifier'])) {
        throw new Exception('Missing required fields');
    }
    
    $postId = intval($input['post_id']);
    $voteType = $input['vote_type'];
    $voterNullifier = $input['voter_nullifier'];
    
    if (!in_array($voteType, ['upvote', 'downvote'])) {
        throw new Exception('Invalid vote type');
    }
    
    // Check post exists
    $stmt = $pdo->prepare('SELECT id, nullifier FROM anonymous_posts WHERE id = ? AND is_removed = FALSE');
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        throw new Exception('Post not found');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Check if already voted
        $stmt = $pdo->prepare('SELECT vote_type FROM anonymous_votes WHERE post_id = ? AND voter_nullifier = ?');
        $stmt->execute([$postId, $voterNullifier]);
        $existingVote = $stmt->fetch();
        
        if ($existingVote) {
            if ($existingVote['vote_type'] === $voteType) {
                throw new Exception('Already voted');
            }
            
            // Change vote
            $stmt = $pdo->prepare('UPDATE anonymous_votes SET vote_type = ? WHERE post_id = ? AND voter_nullifier = ?');
            $stmt->execute([$voteType, $postId, $voterNullifier]);
            
            // Update counts
            if ($voteType === 'upvote') {
                $stmt = $pdo->prepare('UPDATE anonymous_posts SET upvotes = upvotes + 1, downvotes = downvotes - 1, reputation_score = reputation_score + 2 WHERE id = ?');
            } else {
                $stmt = $pdo->prepare('UPDATE anonymous_posts SET downvotes = downvotes + 1, upvotes = upvotes - 1, reputation_score = reputation_score - 2 WHERE id = ?');
            }
            $stmt->execute([$postId]);
            
        } else {
            // New vote
            $stmt = $pdo->prepare('INSERT INTO anonymous_votes (post_id, voter_nullifier, vote_type) VALUES (?, ?, ?)');
            $stmt->execute([$postId, $voterNullifier, $voteType]);
            
            // Update counts
            if ($voteType === 'upvote') {
                $stmt = $pdo->prepare('UPDATE anonymous_posts SET upvotes = upvotes + 1, reputation_score = reputation_score + 1 WHERE id = ?');
            } else {
                $stmt = $pdo->prepare('UPDATE anonymous_posts SET downvotes = downvotes + 1, reputation_score = reputation_score - 1 WHERE id = ?');
            }
            $stmt->execute([$postId]);
        }
        
        // Update author reputation
        if ($voteType === 'upvote') {
            $stmt = $pdo->prepare('UPDATE anonymous_reputation SET upvotes_received = upvotes_received + 1, reputation_score = reputation_score + 1 WHERE nullifier = ?');
        } else {
            $stmt = $pdo->prepare('UPDATE anonymous_reputation SET downvotes_received = downvotes_received + 1, reputation_score = reputation_score - 1 WHERE nullifier = ?');
        }
        $stmt->execute([$post['nullifier']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Vote registered'
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
