<?php
/**
 * ============================================
 * P2P SEND MESSAGE API
 * ============================================
 * Fallback para envío de mensajes cuando WebRTC no está disponible
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

if (!isset($input['recipientId']) || !isset($input['encryptedData'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$senderId = $_SESSION['user_id'];
$recipientId = intval($input['recipientId']);
$encryptedData = $input['encryptedData'];
$metadata = isset($input['metadata']) ? json_encode($input['metadata']) : null;

try {
    // Guardar mensaje en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO p2p_messages (sender_id, recipient_id, encrypted_data, metadata)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$senderId, $recipientId, $encryptedData, $metadata]);
    
    $messageId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent',
        'messageId' => $messageId
    ]);
    
} catch (PDOException $e) {
    error_log("Error sending P2P message: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
