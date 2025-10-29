<?php
/**
 * ============================================
 * P2P METADATA GET BY CID API
 * ============================================
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../cors_helper.php';
require_once __DIR__ . '/../session_helpers.php';

handleCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verificar sesión
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Obtener CID de la URL
$path = $_SERVER['REQUEST_URI'];
$parts = explode('/', $path);
$cid = end($parts);

if (empty($cid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM p2p_metadata 
        WHERE cid = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$cid]);
    $metadata = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$metadata) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Metadata not found'
        ]);
        exit;
    }
    
    // Decodificar JSON fields
    $metadata['recipient_ids'] = json_decode($metadata['recipient_ids'], true);
    $metadata['wrapped_keys'] = json_decode($metadata['wrapped_keys'], true);
    $storedMetadata = json_decode($metadata['metadata'], true);
    
    // Verificar que el usuario actual es recipiente
    $userId = $_SESSION['user_id'];
    if (!in_array($userId, $metadata['recipient_ids']) && $metadata['sender_id'] != $userId) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ]);
        exit;
    }
    
    // Reconstruir objeto compatible con cliente
    $response = [
        'cid' => $metadata['cid'],
        'ciphertext' => $storedMetadata['ciphertext'] ?? '',
        'iv' => $metadata['iv'],
        'wrappedKeys' => $metadata['wrapped_keys'],
        'senderPub' => $storedMetadata['senderPub'] ?? null,
        'senderId' => $metadata['sender_id'],
        'recipients' => $metadata['recipient_ids'],
        'meta' => $storedMetadata['meta'] ?? [],
        'ts' => $metadata['timestamp']
    ];
    
    echo json_encode([
        'success' => true,
        'metadata' => $response
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching P2P metadata: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
