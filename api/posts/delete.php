<?php
/**
 * API: DELETE POST
 * Delete a post (soft delete)
 */

require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'] ?? null;

if (!$post_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'post_id required']);
    exit;
}

try {
    // Get post
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    // Check if user is admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_admin = ($user && $user['role'] === 'admin');

    // Verify ownership or admin
    if ($post['user_id'] != $user_id && !$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only delete your own posts']);
        exit;
    }

    // Soft delete
    $stmt = $pdo->prepare("
        UPDATE posts
        SET deleted_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$post_id]);

    // Update post count in community
    if ($post['community_id']) {
        $stmt = $pdo->prepare("
            UPDATE communities
            SET total_posts = GREATEST(0, total_posts - 1)
            WHERE id = ?
        ");
        $stmt->execute([$post['community_id']]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Post deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete post'
    ]);
}
?>
