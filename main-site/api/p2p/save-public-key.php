<?php
/**
 * ============================================
 * P2P SAVE PUBLIC KEY API
 * ============================================
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../cors_helper.php';
require_once __DIR__ . '/../session_helpers.php';

handleCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['publicKey'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Public key required']);
    exit;
}

try {
    // Usar INSERT ... ON DUPLICATE KEY UPDATE para manejar nuevas entradas y actualizaciones
    $stmt = $pdo->prepare("
        INSERT INTO x25519_keys (user_id, public_key) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE 
        public_key = VALUES(public_key),
        updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$_SESSION['user_id'], $input['publicKey']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Public key saved'
    ]);
    
} catch (PDOException $e) {
    error_log("Error saving P2P public key: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
