<?php
/**
 * API: DELETE COMMUNITY
 * Delete a community (soft delete)
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
$community_id = $data['community_id'] ?? null;

if (!$community_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'community_id required']);
    exit;
}

try {
    // Get community
    $stmt = $pdo->prepare("SELECT * FROM communities WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$community_id]);
    $community = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$community) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Community not found']);
        exit;
    }

    // Check if user is admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_admin = ($user && $user['role'] === 'admin');

    // Verify ownership or admin
    if ($community['creator_id'] != $user_id && !$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only the creator or admins can delete this community']);
        exit;
    }

    // Check if community has posts
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM posts WHERE community_id = ? AND deleted_at IS NULL");
    $stmt->execute([$community_id]);
    $post_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($post_count > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Cannot delete community with {$post_count} active posts. Delete all posts first."
        ]);
        exit;
    }

    // Soft delete community
    $stmt = $pdo->prepare("
        UPDATE communities
        SET status = 'deleted',
            deleted_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$community_id]);

    // Notify all members
    $stmt = $pdo->prepare("
        SELECT user_id FROM community_members
        WHERE community_id = ? AND user_id != ?
    ");
    $stmt->execute([$community_id, $user_id]);
    $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($members)) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, 'community', 'Community Deleted', ?, ?)
        ");

        $message = "The community '{$community['name']}' has been deleted";
        $notification_data = json_encode(['community_id' => $community_id]);

        foreach ($members as $member_id) {
            try {
                $stmt->execute([$member_id, $message, $notification_data]);
            } catch (Exception $e) {
                error_log("Error notifying member {$member_id}: " . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Community deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete community'
    ]);
}
?>
