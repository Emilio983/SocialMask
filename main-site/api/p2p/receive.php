<?php
/**
 * ============================================
 * P2P RECEIVE MESSAGES API
 * ============================================
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../cors_helper.php';
require_once __DIR__ . '/../session_helpers.php';

handleCORS();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$lastId = isset($_GET['lastId']) ? intval($_GET['lastId']) : 0;

try {
    // Obtener mensajes nuevos para este usuario
    $stmt = $pdo->prepare("
        SELECT 
            id,
            sender_id,
            encrypted_data,
            metadata,
            created_at
        FROM p2p_messages
        WHERE recipient_id = ? AND id > ?
        ORDER BY id ASC
        LIMIT 50
    ");
    $stmt->execute([$userId, $lastId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
    
} catch (PDOException $e) {
    error_log("Error receiving P2P messages: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
