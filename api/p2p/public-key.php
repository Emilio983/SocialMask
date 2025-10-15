<?php
/**
 * ============================================
 * P2P PUBLIC KEY API
 * ============================================
 * Endpoint para obtener llave pública de un usuario
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../cors_helper.php';

// Habilitar CORS
handleCORS();

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Obtener userId del query string
$userId = isset($_GET['userId']) ? intval($_GET['userId']) : 0;

if (!$userId || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid user ID required']);
    exit;
}

try {
    // Buscar llave pública del usuario en x25519_keys
    $stmt = $pdo->prepare("
        SELECT public_key 
        FROM x25519_keys 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $key = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$key) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User has no P2P public key'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'publicKey' => $key['public_key']
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching P2P public key: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
