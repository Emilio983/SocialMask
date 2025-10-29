<?php
// ============================================
// BLOCK/UNBLOCK USER API
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
    $blocker_id = $_SESSION['user_id'];
    $blocked_id = $data['user_id'] ?? null;
    $action = $data['action'] ?? 'block'; // 'block' or 'unblock'
    $reason = $data['reason'] ?? null;

    if (!$blocked_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'user_id is required']);
        exit;
    }

    if ($blocker_id == $blocked_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot block yourself']);
        exit;
    }

    if ($action === 'block') {
        // Insertar bloqueo
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id, reason) VALUES (?, ?, ?)");
        $stmt->execute([$blocker_id, $blocked_id, $reason]);

        // Eliminar seguimientos mutuos
        $stmt = $pdo->prepare("DELETE FROM user_followers WHERE (follower_id = ? AND following_id = ?) OR (follower_id = ? AND following_id = ?)");
        $stmt->execute([$blocker_id, $blocked_id, $blocked_id, $blocker_id]);

        $message = 'User blocked successfully';
    } else {
        // Eliminar bloqueo
        $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$blocker_id, $blocked_id]);

        $message = 'User unblocked successfully';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'is_blocked' => ($action === 'block')
    ]);

} catch (Exception $e) {
    error_log("ERROR - block_user.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
