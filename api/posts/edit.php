<?php
/**
 * API: EDIT POST
 * Edit post content (only within 15 minutes of creation)
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
$new_content = $data['content'] ?? null;

if (!$post_id || !$new_content) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Sanitize
$new_content = trim($new_content);
if (strlen($new_content) < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content cannot be empty']);
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

    // Verify ownership
    if ($post['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only edit your own posts']);
        exit;
    }

    // Check time limit (15 minutes)
    $created_time = strtotime($post['created_at']);
    $current_time = time();
    $minutes_elapsed = ($current_time - $created_time) / 60;

    if ($minutes_elapsed > 15) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Posts can only be edited within 15 minutes of creation'
        ]);
        exit;
    }

    // Update post
    $stmt = $pdo->prepare("
        UPDATE posts
        SET
            content = ?,
            edited_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$new_content, $post_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Post updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update post'
    ]);
}
?>
