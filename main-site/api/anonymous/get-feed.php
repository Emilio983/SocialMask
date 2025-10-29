<?php
/**
 * Get Anonymous Feed
 * Retrieve verified anonymous posts
 */

require_once '../../config/config.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
    $limit = isset($input['limit']) ? min(50, max(1, intval($input['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Get posts
    $stmt = $pdo->prepare('
        SELECT 
            ap.id,
            ap.content,
            ap.content_type,
            ap.media_url,
            ap.reputation_score,
            ap.upvotes,
            ap.downvotes,
            ap.views,
            ap.comments_count,
            ap.verified,
            ap.created_at,
            ar.reputation_score as author_reputation,
            ar.badges,
            ar.is_verified as author_verified
        FROM anonymous_posts ap
        LEFT JOIN anonymous_reputation ar ON ap.nullifier = ar.nullifier
        WHERE ap.is_removed = FALSE
            AND ap.verified = TRUE
        ORDER BY ap.created_at DESC
        LIMIT ? OFFSET ?
    ');
    
    $stmt->execute([$limit, $offset]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $stmt = $pdo->query('SELECT COUNT(*) FROM anonymous_posts WHERE is_removed = FALSE AND verified = TRUE');
    $totalPosts = $stmt->fetchColumn();
    
    // Format posts
    foreach ($posts as &$post) {
        $post['badges'] = json_decode($post['badges'] ?? '[]', true);
        $post['is_anonymous'] = true;
        $post['author'] = [
            'type' => 'anonymous',
            'reputation' => $post['author_reputation'] ?? 0,
            'verified' => (bool)($post['author_verified'] ?? false),
            'badges' => $post['badges']
        ];
        
        unset($post['author_reputation']);
        unset($post['author_verified']);
    }
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalPosts,
            'pages' => ceil($totalPosts / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
