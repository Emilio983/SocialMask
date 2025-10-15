<?php
/**
 * Get X25519 Public Key
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'user_id required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT public_key, created_at, updated_at FROM x25519_keys WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'public_key' => $result['public_key'],
            'created_at' => $result['created_at'],
            'updated_at' => $result['updated_at']
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Public key not found'
        ]);
    }

} catch (PDOException $e) {
    error_log("X25519 key fetch error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
