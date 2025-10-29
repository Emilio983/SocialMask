<?php
/**
 * ============================================
 * P2P STATISTICS API
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

try {
    $pdo = getConnection();
    $userId = $_SESSION['user_id'];
    
    // Obtener/crear estadísticas del usuario
    $stmt = $pdo->prepare("
        SELECT * FROM p2p_stats WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stats) {
        // Crear registro inicial
        $stmt = $pdo->prepare("
            INSERT INTO p2p_stats (user_id) VALUES (?)
        ");
        $stmt->execute([$userId]);
        
        $stats = [
            'user_id' => $userId,
            'total_p2p_messages' => 0,
            'total_p2p_posts' => 0,
            'total_ipfs_uploads' => 0,
            'storage_used' => 0,
            'last_p2p_activity' => null
        ];
    }
    
    // Obtener estadísticas en tiempo real
    
    // Posts P2P
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM posts 
        WHERE user_id = ? AND p2p_mode = 1
    ");
    $stmt->execute([$userId]);
    $p2pPosts = $stmt->fetchColumn();
    
    // Mensajes P2P
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM messages 
        WHERE sender_id = ? AND p2p_mode = 1
    ");
    $stmt->execute([$userId]);
    $p2pMessages = $stmt->fetchColumn();
    
    // Uploads IPFS
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM p2p_metadata 
        WHERE sender_id = ?
    ");
    $stmt->execute([$userId]);
    $ipfsUploads = $stmt->fetchColumn();
    
    // Preferencias P2P
    $stmt = $pdo->prepare("
        SELECT * FROM user_p2p_preferences WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prefs) {
        $prefs = [
            'p2p_enabled' => false,
            'auto_sync' => true,
            'encryption_enabled' => true
        ];
    }
    
    // Actualizar estadísticas
    $stmt = $pdo->prepare("
        UPDATE p2p_stats 
        SET total_p2p_messages = ?,
            total_p2p_posts = ?,
            total_ipfs_uploads = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$p2pMessages, $p2pPosts, $ipfsUploads, $userId]);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'p2p_posts' => (int)$p2pPosts,
            'p2p_messages' => (int)$p2pMessages,
            'ipfs_uploads' => (int)$ipfsUploads,
            'storage_used' => (int)$stats['storage_used'],
            'last_activity' => $stats['last_p2p_activity']
        ],
        'preferences' => [
            'p2p_enabled' => (bool)$prefs['p2p_enabled'],
            'auto_sync' => (bool)$prefs['auto_sync'],
            'encryption_enabled' => (bool)$prefs['encryption_enabled']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
