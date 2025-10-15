<?php
// ============================================
// FOLLOW/UNFOLLOW USER API
// ============================================

require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $follower_id = $_SESSION['user_id'];
    $following_id = $data['user_id'] ?? null;
    $action = $data['action'] ?? 'follow'; // 'follow' or 'unfollow'

    if (!$following_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'user_id is required']);
        exit;
    }

    if ($follower_id == $following_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot follow yourself']);
        exit;
    }

    // Verificar si el usuario está bloqueado
    $stmt = $pdo->prepare("SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
    $stmt->execute([$follower_id, $following_id, $following_id, $follower_id]);
    if ($stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot follow this user']);
        exit;
    }

    if ($action === 'follow') {
        // Insertar seguimiento
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_followers (follower_id, following_id) VALUES (?, ?)");
        $stmt->execute([$follower_id, $following_id]);
        $message = 'Successfully followed user';
    } else {
        // Eliminar seguimiento
        $stmt = $pdo->prepare("DELETE FROM user_followers WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$follower_id, $following_id]);
        $message = 'Successfully unfollowed user';
    }

    // Obtener contadores actualizados
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_followers WHERE following_id = ?");
    $stmt->execute([$following_id]);
    $followers_count = $stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'message' => $message,
        'followers_count' => $followers_count,
        'is_following' => ($action === 'follow')
    ]);

} catch (Exception $e) {
    error_log("ERROR - follow_user.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
