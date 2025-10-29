<?php
/**
 * ============================================
 * P2P METADATA STORE API
 * ============================================
 * Endpoint para almacenar metadatos P2P
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../cors_helper.php';
require_once __DIR__ . '/../session_helpers.php';

// Habilitar CORS
handleCORS();

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Validar campos requeridos
$required = ['ciphertext', 'iv', 'senderId', 'recipients', 'wrappedKeys', 'ts'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]);
        exit;
    }
}

try {
    // Generar CID único
    $cid = bin2hex(random_bytes(32));
    
    // Almacenar el ciphertext completo en metadata (incluye senderPub y ciphertext)
    $metadataJson = json_encode([
        'ciphertext' => $input['ciphertext'],
        'senderPub' => $input['senderPub'] ?? null,
        'meta' => $input['meta'] ?? []
    ]);
    
    // Insertar metadata
    $stmt = $pdo->prepare("
        INSERT INTO p2p_metadata 
        (cid, iv, sender_id, recipient_ids, wrapped_keys, timestamp, metadata, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $cid,
        $input['iv'],
        $input['senderId'],
        json_encode($input['recipients']),
        json_encode($input['wrappedKeys']),
        $input['ts'],
        $metadataJson
    ]);
    
    echo json_encode([
        'success' => true,
        'cid' => $cid,
        'id' => $pdo->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    error_log("Error storing P2P metadata: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
