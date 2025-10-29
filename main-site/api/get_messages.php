<?php
// ============================================
// GET MESSAGES API
// Obtiene mensajes de una conversación
// ============================================

require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $conversation_id = $_GET['conversation_id'] ?? null;
    $other_user_id = $_GET['other_user_id'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    $before_message_id = $_GET['before_message_id'] ?? null;

    // Obtener conversation_id si se proporcionó other_user_id
    if (!$conversation_id && $other_user_id) {
        $user1_id = min($user_id, $other_user_id);
        $user2_id = max($user_id, $other_user_id);

        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?");
        $stmt->execute([$user1_id, $user2_id]);
        $conv = $stmt->fetch();

        if (!$conv) {
            // No hay conversación aún
            echo json_encode([
                'success' => true,
                'messages' => [],
                'other_user' => null
            ]);
            exit;
        }

        $conversation_id = $conv['id'];
    }

    if (!$conversation_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'conversation_id or other_user_id is required']);
        exit;
    }

    // Verificar que el usuario es parte de la conversación
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // Determinar el otro usuario
    $other_user_id = ($conversation['user1_id'] == $user_id) ? $conversation['user2_id'] : $conversation['user1_id'];

    // Obtener información del otro usuario
    $stmt = $pdo->prepare("SELECT user_id, username, profile_image, membership_plan FROM users WHERE user_id = ?");
    $stmt->execute([$other_user_id]);
    $other_user = $stmt->fetch();

    // Construir query de mensajes
    $query = "
        SELECT
            m.*,
            u.username as sender_username,
            u.profile_image as sender_profile_image
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.conversation_id = ?
        AND NOT (
            (m.sender_id = ? AND m.is_deleted_by_sender = TRUE) OR
            (m.receiver_id = ? AND m.is_deleted_by_receiver = TRUE)
        )
    ";

    $params = [$conversation_id, $user_id, $user_id];

    if ($before_message_id) {
        $query .= " AND m.id < ?";
        $params[] = $before_message_id;
    }

    $query .= " ORDER BY m.created_at DESC LIMIT ?";
    $params[] = (int)$limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $messages = array_reverse($stmt->fetchAll());

    // Marcar mensajes como leídos
    $stmt = $pdo->prepare("CALL MarkMessagesAsRead(?, ?)");
    $stmt->execute([$conversation_id, $user_id]);

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'other_user' => $other_user,
        'conversation_id' => $conversation_id
    ]);

} catch (Exception $e) {
    error_log("ERROR - get_messages.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
