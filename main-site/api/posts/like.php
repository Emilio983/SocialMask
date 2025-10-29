<?php
/**
 * API: LIKE/UNLIKE POST
 * Toggle like on a post
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
    // Verify post exists
    $stmt = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    // Check if already liked
    $stmt = $pdo->prepare("
        SELECT id FROM interactions
        WHERE user_id = ? AND target_type = 'post' AND target_id = ? AND interaction_type = 'like'
    ");
    $stmt->execute([$user_id, $post_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Unlike
        $stmt = $pdo->prepare("DELETE FROM interactions WHERE id = ?");
        $stmt->execute([$existing['id']]);

        // Update post upvotes
        $stmt = $pdo->prepare("UPDATE posts SET upvotes = GREATEST(0, upvotes - 1) WHERE id = ?");
        $stmt->execute([$post_id]);

        $action = 'unliked';
    } else {
        // Like
        $stmt = $pdo->prepare("
            INSERT INTO interactions (user_id, target_type, target_id, interaction_type, created_at)
            VALUES (?, 'post', ?, 'like', NOW())
        ");
        $stmt->execute([$user_id, $post_id]);

        // Update post upvotes
        $stmt = $pdo->prepare("UPDATE posts SET upvotes = upvotes + 1 WHERE id = ?");
        $stmt->execute([$post_id]);

        // Notify post owner (if not liking own post)
        if ($post['user_id'] != $user_id) {
            try {
                $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $liker = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, data)
                    VALUES (?, 'like', 'New Like', ?, ?)
                ");

                $message = "@{$liker['username']} liked your post";
                $notification_data = json_encode(['post_id' => $post_id]);
                $stmt->execute([$post['user_id'], $message, $notification_data]);
            } catch (Exception $e) {
                error_log("Error sending notification: " . $e->getMessage());
            }
        }

        $action = 'liked';
    }

    // Get updated like count
    $stmt = $pdo->prepare("SELECT upvotes FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $updated_post = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'action' => $action,
        'like_count' => $updated_post['upvotes']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process like'
    ]);
}
?>
