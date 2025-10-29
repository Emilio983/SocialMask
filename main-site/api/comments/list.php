<?php
/**
 * API: LIST COMMENTS
 * Get comments for a post with pagination
 */

require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');

$post_id = $_GET['post_id'] ?? null;
$parent_id = $_GET['parent_id'] ?? null; // For loading replies
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

if (!$post_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'post_id required']);
    exit;
}

try {
    // Build query based on parent_id
    if ($parent_id !== null) {
        // Get replies to a specific comment
        $stmt = $pdo->prepare("
            SELECT
                c.*,
                u.username,
                u.unique_username,
                u.profile_image,
                u.is_verified,
                u.membership_plan,
                (SELECT COUNT(*) FROM interactions WHERE target_type = 'comment' AND target_id = c.id AND interaction_type = 'like') as like_count
            FROM comments c
            INNER JOIN users u ON c.user_id = u.user_id
            WHERE c.post_id = ? AND c.parent_comment_id = ?
            ORDER BY c.created_at ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$post_id, $parent_id, $limit, $offset]);
    } else {
        // Get top-level comments (no parent)
        $stmt = $pdo->prepare("
            SELECT
                c.*,
                u.username,
                u.unique_username,
                u.profile_image,
                u.is_verified,
                u.membership_plan,
                (SELECT COUNT(*) FROM interactions WHERE target_type = 'comment' AND target_id = c.id AND interaction_type = 'like') as like_count,
                (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.id) as reply_count
            FROM comments c
            INNER JOIN users u ON c.user_id = u.user_id
            WHERE c.post_id = ? AND c.parent_comment_id IS NULL
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$post_id, $limit, $offset]);
    }

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    if ($parent_id !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM comments WHERE post_id = ? AND parent_comment_id = ?");
        $stmt->execute([$post_id, $parent_id]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM comments WHERE post_id = ? AND parent_comment_id IS NULL");
        $stmt->execute([$post_id]);
    }
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'total' => $total,
        'has_more' => ($offset + $limit) < $total
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load comments'
    ]);
}
?>
