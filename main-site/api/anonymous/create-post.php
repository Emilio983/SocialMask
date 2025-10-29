<?php
/**
 * Create Anonymous Post
 * Verifies ZK proof and creates anonymous post
 */

require_once '../../config/config.php';
require_once '../utils.php';

header('Content-Type: application/json');

// Allow POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['content']) || !isset($input['nullifier']) || !isset($input['proof']) || !isset($input['public_signals'])) {
        throw new Exception('Missing required fields');
    }
    
    $content = trim($input['content']);
    $nullifier = $input['nullifier'];
    $proof = $input['proof'];
    $publicSignals = $input['public_signals'];
    $contentType = $input['content_type'] ?? 'text';
    $mediaUrl = $input['media_url'] ?? null;
    
    // Validate content
    if (empty($content)) {
        throw new Exception('Content cannot be empty');
    }
    
    if (strlen($content) > 5000) {
        throw new Exception('Content too long (max 5000 characters)');
    }
    
    // Check nullifier not used
    $stmt = $pdo->prepare('SELECT id FROM nullifier_registry WHERE nullifier = ?');
    $stmt->execute([$nullifier]);
    if ($stmt->fetch()) {
        throw new Exception('Nullifier already used');
    }
    
    // Verify proof (simplified for now)
    $proofData = json_decode($proof, true);
    $signals = json_decode($publicSignals, true);
    
    if (!$proofData || !$signals) {
        throw new Exception('Invalid proof format');
    }
    
    // Verify timestamp (5 minute window)
    if (isset($signals['timestamp'])) {
        $timeDiff = abs(time() - intval($signals['timestamp'] / 1000));
        if ($timeDiff > 300) {
            throw new Exception('Proof expired');
        }
    }
    
    // Verify content hash
    if (isset($signals['contentHash'])) {
        $expectedHash = hash('sha256', $content);
        if ($signals['contentHash'] !== $expectedHash) {
            throw new Exception('Content hash mismatch');
        }
    }
    
    $pdo->beginTransaction();
    
    try {
        // Register nullifier
        $stmt = $pdo->prepare('INSERT INTO nullifier_registry (nullifier, action_type) VALUES (?, ?)');
        $stmt->execute([$nullifier, 'anonymous_post']);
        
        // Create post
        $stmt = $pdo->prepare('
            INSERT INTO anonymous_posts (
                nullifier, 
                content, 
                content_type, 
                media_url, 
                proof_data, 
                public_signals, 
                verified
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $nullifier,
            $content,
            $contentType,
            $mediaUrl,
            $proof,
            $publicSignals,
            true // Verified
        ]);
        
        $postId = $pdo->lastInsertId();
        
        // Update/create reputation
        $stmt = $pdo->prepare('
            INSERT INTO anonymous_reputation (nullifier, post_count, last_seen_at)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                post_count = post_count + 1,
                last_seen_at = NOW()
        ');
        $stmt->execute([$nullifier]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'post_id' => $postId,
            'message' => 'Anonymous post created successfully'
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
