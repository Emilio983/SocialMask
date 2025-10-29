<?php
/**
 * ============================================
 * GET POSTS - HYBRID P2P/CENTRALIZADO
 * ============================================
 */

require_once __DIR__ . '/../../config/session-config.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    $communityId = $_GET['community_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Construir query
    $where = [];
    $params = [];
    
    if ($communityId) {
        $where[] = "p.community_id = ?";
        $params[] = $communityId;
    }
    
    if ($userId) {
        $where[] = "p.user_id = ?";
        $params[] = $userId;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Query principal
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.full_name,
            u.profile_picture,
            COUNT(DISTINCT l.id) as likes_count,
            COUNT(DISTINCT c.id) as comments_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked
        FROM posts p
        INNER JOIN users u ON p.user_id = u.id
        LEFT JOIN likes l ON p.id = l.post_id
        LEFT JOIN comments c ON p.id = c.post_id
        {$whereClause}
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params = array_merge([isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0], $params, [$limit, $offset]);
    $stmt->execute($params);
    
    $posts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Procesar imágenes
        $images = [];
        if ($row['images']) {
            $images = json_decode($row['images'], true) ?? [];
        }
        
        // Procesar IPFS hashes (modo P2P)
        $ipfsHashes = [];
        if ($row['ipfs_hashes']) {
            $ipfsHashes = json_decode($row['ipfs_hashes'], true) ?? [];
            
            // Convertir a URLs de gateway
            $ipfsUrls = array_map(function($hash) {
                return "https://gateway.pinata.cloud/ipfs/{$hash}";
            }, $ipfsHashes);
            
            // Si es P2P, usar URLs de IPFS en lugar de locales
            if ($row['p2p_mode']) {
                $images = $ipfsUrls;
            }
        }
        
        $posts[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'profile_picture' => $row['profile_picture'],
            'content' => $row['content'],
            'images' => $images,
            'ipfs_hashes' => $ipfsHashes,
            'p2p_mode' => (bool)$row['p2p_mode'],
            'community_id' => $row['community_id'],
            'created_at' => $row['created_at'],
            'likes_count' => (int)$row['likes_count'],
            'comments_count' => (int)$row['comments_count'],
            'user_liked' => (bool)$row['user_liked']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'count' => count($posts)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
