<?php
/**
 * API: CREATE COMMENT
 * Create a comment on a post
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

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

$post_id = $data['post_id'] ?? null;
$content = $data['content'] ?? null;
$parent_comment_id = $data['parent_comment_id'] ?? null; // For replies

// Validate
if (!$post_id || !$content) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Sanitize content
$content = trim($content);
if (strlen($content) < 1 || strlen($content) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment must be between 1 and 5000 characters']);
    exit;
}

try {
    // Verify post exists
    $stmt = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    // If replying, verify parent comment exists
    if ($parent_comment_id) {
        $stmt = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND post_id = ?");
        $stmt->execute([$parent_comment_id, $post_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Parent comment not found']);
            exit;
        }
    }

    // Create comment
    $stmt = $pdo->prepare("
        INSERT INTO comments (
            post_id,
            user_id,
            parent_comment_id,
            content,
            created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $post_id,
        $user_id,
        $parent_comment_id,
        $content
    ]);

    $comment_id = $pdo->lastInsertId();

    // Update comment count in post
    $stmt = $pdo->prepare("
        UPDATE posts
        SET comment_count = comment_count + 1
        WHERE id = ?
    ");
    $stmt->execute([$post_id]);

    // Notify post owner (if not commenting on own post)
    if ($post['user_id'] != $user_id) {
        try {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $commenter = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, data)
                VALUES (?, 'comment', 'New Comment', ?, ?)
            ");

            $message = "@{$commenter['username']} commented on your post";
            $notification_data = json_encode(['post_id' => $post_id, 'comment_id' => $comment_id]);
            $stmt->execute([$post['user_id'], $message, $notification_data]);
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
        }
    }

    // Get created comment with user info
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            u.username,
            u.profile_image,
            u.is_verified
        FROM comments c
        INNER JOIN users u ON c.user_id = u.user_id
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Comment created',
        'comment' => $comment
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create comment'
    ]);
}
?>
