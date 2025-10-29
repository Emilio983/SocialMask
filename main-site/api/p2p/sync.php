<?php
/**
 * ============================================
 * P2P SYNC API - Sincronización bidireccional
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $action = $data['action'] ?? 'sync_all';
    $userId = $_SESSION['user_id'];
    
    $pdo = getConnection();
    $synced = [];
    
    switch ($action) {
        case 'sync_posts':
            // Sincronizar posts de P2P a centralizado
            $stmt = $pdo->prepare("
                SELECT id, content, images, ipfs_hashes 
                FROM posts 
                WHERE user_id = ? AND p2p_mode = 1
            ");
            $stmt->execute([$userId]);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $synced['posts'] = count($posts);
            break;
            
        case 'sync_messages':
            // Sincronizar mensajes de P2P a centralizado
            $stmt = $pdo->prepare("
                SELECT id, recipient_id, message, encrypted_data 
                FROM messages 
                WHERE sender_id = ? AND p2p_mode = 1
            ");
            $stmt->execute([$userId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $synced['messages'] = count($messages);
            break;
            
        case 'sync_all':
            // Sincronizar todo
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM posts 
                WHERE user_id = ? AND p2p_mode = 1
            ");
            $stmt->execute([$userId]);
            $synced['posts'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM messages 
                WHERE sender_id = ? AND p2p_mode = 1
            ");
            $stmt->execute([$userId]);
            $synced['messages'] = $stmt->fetchColumn();
            
            break;
            
        case 'enable_p2p':
            // Habilitar modo P2P para usuario
            $stmt = $pdo->prepare("
                INSERT INTO user_p2p_preferences (user_id, p2p_enabled) 
                VALUES (?, 1)
                ON DUPLICATE KEY UPDATE p2p_enabled = 1
            ");
            $stmt->execute([$userId]);
            $synced['p2p_enabled'] = true;
            break;
            
        case 'disable_p2p':
            // Deshabilitar modo P2P
            $stmt = $pdo->prepare("
                INSERT INTO user_p2p_preferences (user_id, p2p_enabled) 
                VALUES (?, 0)
                ON DUPLICATE KEY UPDATE p2p_enabled = 0
            ");
            $stmt->execute([$userId]);
            $synced['p2p_enabled'] = false;
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
    // Actualizar última actividad
    $stmt = $pdo->prepare("
        UPDATE p2p_stats 
        SET last_p2p_activity = NOW() 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'synced' => $synced,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
