<?php
/**
 * Check Nullifier
 * Verify if nullifier has been used (prevent double-spending)
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['nullifier'])) {
        throw new Exception('Nullifier required');
    }
    
    $nullifier = $input['nullifier'];
    
    // Check in registry
    $stmt = $pdo->prepare('SELECT action_type, used_at FROM nullifier_registry WHERE nullifier = ?');
    $stmt->execute([$nullifier]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode([
            'success' => true,
            'used' => true,
            'action_type' => $record['action_type'],
            'used_at' => $record['used_at']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'used' => false
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
