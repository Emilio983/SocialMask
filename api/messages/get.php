<?php
/**
 * ============================================
 * GET MESSAGES - HYBRID P2P/CENTRALIZADO
 * ============================================
 */

require_once __DIR__ . '/../../config/session-config.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    $otherUserId = $_GET['user_id'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    if (!$otherUserId) {
        throw new Exception('Falta ID del otro usuario');
    }
    
    // Obtener mensajes entre los dos usuarios
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            u.username as sender_username,
            u.full_name as sender_name,
            u.profile_picture as sender_picture
        FROM messages m
        INNER JOIN users u ON m.sender_id = u.id
        WHERE 
            (m.sender_id = ? AND m.recipient_id = ?)
            OR
            (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at ASC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([
        $_SESSION['user_id'], $otherUserId,
        $otherUserId, $_SESSION['user_id'],
        $limit, $offset
    ]);
    
    $messages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'recipient_id' => $row['recipient_id'],
            'sender_username' => $row['sender_username'],
            'sender_name' => $row['sender_name'],
            'sender_picture' => $row['sender_picture'],
            'message' => $row['message'],
            'encrypted_data' => $row['encrypted_data'],
            'p2p_mode' => (bool)$row['p2p_mode'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'is_mine' => $row['sender_id'] == $_SESSION['user_id']
        ];
    }
    
    // Marcar como leídos los mensajes recibidos
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE recipient_id = ? AND sender_id = ? AND is_read = 0
    ");
    $stmt->execute([$_SESSION['user_id'], $otherUserId]);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
