<?php
/**
 * ============================================
 * LIST POSTS BY GROUP - HYBRID P2P/CENTRALIZADO
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    $groupId = $_GET['group_id'] ?? null;
    $communityId = $_GET['community_id'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    if (!$groupId && !$communityId) {
        throw new Exception('Se requiere group_id o community_id');
    }

    // Construir query
    $where = [];
    $params = [];

    if ($groupId) {
        $where[] = "p.group_id = ?";
        $params[] = $groupId;
    }

    if ($communityId) {
        $where[] = "p.community_id = ?";
        $params[] = $communityId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Query principal - Obtiene TODOS los posts (P2P y centralizados)
    // Verificar si existen las tablas de likes y comments
    $hasLikesTable = false;
    $hasCommentsTable = false;

    try {
        $pdo->query("SELECT 1 FROM likes LIMIT 1");
        $hasLikesTable = true;
    } catch (PDOException $e) {
        // Tabla no existe
    }

    try {
        $pdo->query("SELECT 1 FROM comments LIMIT 1");
        $hasCommentsTable = true;
    } catch (PDOException $e) {
        // Tabla no existe
    }

    // Construir query según tablas disponibles
    if ($hasLikesTable && $hasCommentsTable) {
        $stmt = $pdo->prepare("
            SELECT
                p.*,
                u.username,
                u.username as full_name,
                u.profile_image as profile_picture,
                COUNT(DISTINCT l.id) as likes_count,
                COUNT(DISTINCT c.id) as comments_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked
            FROM posts p
            INNER JOIN users u ON p.user_id = u.user_id
            LEFT JOIN likes l ON p.id = l.post_id
            LEFT JOIN comments c ON p.id = c.post_id
            {$whereClause}
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $execParams = array_merge(
            [isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0],
            $params,
            [$limit, $offset]
        );
    } else {
        // Query simplificado sin likes/comments
        $stmt = $pdo->prepare("
            SELECT
                p.*,
                u.username,
                u.username as full_name,
                u.profile_image as profile_picture,
                0 as likes_count,
                0 as comments_count,
                0 as user_liked
            FROM posts p
            INNER JOIN users u ON p.user_id = u.user_id
            {$whereClause}
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $execParams = array_merge(
            $params,
            [$limit, $offset]
        );
    }

    $stmt->execute($execParams);

    $posts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Procesar imágenes locales
        $images = [];
        if ($row['images']) {
            $images = json_decode($row['images'], true) ?? [];
        }

        // Procesar IPFS hashes (modo P2P)
        $ipfsHashes = [];
        $ipfsUrls = [];

        if ($row['ipfs_hashes']) {
            $ipfsHashes = json_decode($row['ipfs_hashes'], true) ?? [];

            // Convertir a URLs de gateway con fallback
            foreach ($ipfsHashes as $hash) {
                $ipfsUrls[] = [
                    'primary' => "https://gateway.pinata.cloud/ipfs/{$hash}",
                    'fallback1' => "https://ipfs.io/ipfs/{$hash}",
                    'fallback2' => "https://cloudflare-ipfs.com/ipfs/{$hash}"
                ];
            }

            // Si es P2P, usar URLs de IPFS como principal
            if ($row['p2p_mode']) {
                $images = array_map(function($urls) {
                    return $urls['primary'];
                }, $ipfsUrls);
            }
        }

        $posts[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'profile_picture' => $row['profile_picture'],
            'content' => $row['content'],
            'images' => $images,
            'ipfs_hashes' => $ipfsHashes,
            'ipfs_urls' => $ipfsUrls,
            'p2p_mode' => (bool)$row['p2p_mode'],
            'group_id' => $row['group_id'] ? (int)$row['group_id'] : null,
            'community_id' => $row['community_id'] ? (int)$row['community_id'] : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'] ?? null,
            'likes_count' => (int)$row['likes_count'],
            'comments_count' => (int)$row['comments_count'],
            'user_liked' => (bool)$row['user_liked']
        ];
    }

    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'count' => count($posts),
        'has_more' => count($posts) === $limit
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
