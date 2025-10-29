<?php
/**
 * ============================================
 * SEND MESSAGE - HYBRID P2P/CENTRALIZADO
 * ============================================
 */

require_once __DIR__ . '/../../config/session-config.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $recipientId = $data['recipientId'] ?? null;
    $message = $data['message'] ?? '';
    $p2pMode = $data['p2pMode'] ?? false;
    $encryptedData = $data['encryptedData'] ?? null;
    
    if (!$recipientId) {
        throw new Exception('Falta ID del destinatario');
    }
    
    if (empty($message) && empty($encryptedData)) {
        throw new Exception('El mensaje no puede estar vacío');
    }
    
    $pdo = getDBConnection();
    
    // Verificar que el destinatario existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$recipientId]);
    if (!$stmt->fetch()) {
        throw new Exception('Destinatario no encontrado');
    }
    
    // Insertar mensaje
    $stmt = $pdo->prepare("
        INSERT INTO messages (
            sender_id, recipient_id, message, encrypted_data, 
            p2p_mode, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $recipientId,
        $p2pMode ? null : $message, // Si es P2P, no guardar texto plano
        $encryptedData,
        $p2pMode ? 1 : 0
    ]);
    
    $messageId = $pdo->lastInsertId();
    
    // Si es P2P, guardar metadata
    if ($p2pMode && $encryptedData) {
        $stmt = $pdo->prepare("
            INSERT INTO p2p_metadata (
                message_id, sender_id, recipient_ids, 
                metadata, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $metadataJson = json_encode([
            'type' => 'message',
            'messageId' => $messageId,
            'timestamp' => time()
        ]);
        
        $recipientJson = json_encode([$recipientId]);
        
        $stmt->execute([
            "msg_{$messageId}",
            $_SESSION['user_id'],
            $recipientJson,
            $metadataJson
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'messageId' => $messageId,
        'message' => $p2pMode ? 'Mensaje enviado (E2E encriptado)' : 'Mensaje enviado',
        'p2p_mode' => $p2pMode
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
