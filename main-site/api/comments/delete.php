<?php
/**
 * API: DELETE COMMENT
 * Delete a comment (soft delete)
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
$comment_id = $data['comment_id'] ?? null;

if (!$comment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'comment_id required']);
    exit;
}

try {
    // Get comment
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit;
    }

    // Check if user is admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_admin = ($user && $user['role'] === 'admin');

    // Verify ownership or admin
    if ($comment['user_id'] != $user_id && !$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only delete your own comments']);
        exit;
    }

    // Soft delete: mark content as deleted
    $stmt = $pdo->prepare("
        UPDATE comments
        SET content = '[deleted]',
            deleted_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$comment_id]);

    // Update comment count in post
    $stmt = $pdo->prepare("
        UPDATE posts
        SET comment_count = GREATEST(0, comment_count - 1)
        WHERE id = ?
    ");
    $stmt->execute([$comment['post_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Comment deleted'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete comment'
    ]);
}
?>
