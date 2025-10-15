<?php
/**
 * ============================================
 * CREATE POST - HYBRID P2P/CENTRALIZADO
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
    
    $content = $data['content'] ?? '';
    $images = $data['images'] ?? [];
    $p2pMode = $data['p2pMode'] ?? false;
    $ipfsHashes = $data['ipfsHashes'] ?? [];
    $communityId = $data['community_id'] ?? $data['communityId'] ?? null;
    $groupId = $data['group_id'] ?? null;
    
    if (empty($content) && empty($images) && empty($ipfsHashes)) {
        throw new Exception('El post debe tener contenido o imágenes');
    }
    
    $pdo = getDBConnection();
    
    // Obtener info del usuario
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Insertar post
    $stmt = $pdo->prepare("
        INSERT INTO posts (
            user_id, content, images, p2p_mode, ipfs_hashes,
            community_id, group_id, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $imagesJson = !empty($images) ? json_encode($images) : null;
    $ipfsJson = !empty($ipfsHashes) ? json_encode($ipfsHashes) : null;

    $stmt->execute([
        $_SESSION['user_id'],
        $content,
        $imagesJson,
        $p2pMode ? 1 : 0,
        $ipfsJson,
        $communityId,
        $groupId
    ]);
    
    $postId = $pdo->lastInsertId();
    
    // Si es P2P, guardar en metadata
    if ($p2pMode && !empty($ipfsHashes)) {
        $stmt = $pdo->prepare("
            INSERT INTO p2p_metadata (
                message_id, cid, sender_id, metadata, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $messageId = uniqid('post_', true);
        $metadataJson = json_encode([
            'type' => 'post',
            'postId' => $postId,
            'content' => $content,
            'ipfsHashes' => $ipfsHashes,
            'timestamp' => time()
        ]);
        
        $stmt->execute([
            $messageId,
            $ipfsHashes[0] ?? '', // Primer hash como CID principal
            $_SESSION['user_id'],
            $metadataJson
        ]);
    }
    
    // Respuesta
    echo json_encode([
        'success' => true,
        'postId' => $postId,
        'message' => $p2pMode ? 'Post creado en modo P2P' : 'Post creado',
        'post' => [
            'id' => $postId,
            'user_id' => $_SESSION['user_id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'content' => $content,
            'images' => $images,
            'ipfs_hashes' => $ipfsHashes,
            'p2p_mode' => $p2pMode,
            'created_at' => date('Y-m-d H:i:s'),
            'likes_count' => 0,
            'comments_count' => 0
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
