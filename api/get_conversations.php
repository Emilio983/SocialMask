<?php
// ============================================
// GET CONVERSATIONS API
// Obtiene la lista de conversaciones del usuario
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

    // Obtener conversaciones usando la vista
    $stmt = $pdo->prepare("
        SELECT
            c.id as conversation_id,
            c.last_message_at,
            c.last_message_content,
            c.last_message_type,
            c.last_message_sender_id,
            CASE
                WHEN c.user1_id = ? THEN c.user2_id
                ELSE c.user1_id
            END as other_user_id,
            CASE
                WHEN c.user1_id = ? THEN c.user2_username
                ELSE c.user1_username
            END as other_username,
            CASE
                WHEN c.user1_id = ? THEN c.user2_profile_image
                ELSE c.user1_profile_image
            END as other_profile_image,
            CASE
                WHEN c.user1_id = ? THEN c.user1_unread_count
                ELSE c.user2_unread_count
            END as unread_count
        FROM conversations_with_details c
        WHERE (c.user1_id = ? OR c.user2_id = ?)
        AND NOT (
            (c.user1_id = ? AND c.user1_deleted = TRUE) OR
            (c.user2_id = ? AND c.user2_deleted = TRUE)
        )
        ORDER BY c.last_message_at DESC
    ");

    $stmt->execute([
        $user_id, $user_id, $user_id, $user_id,
        $user_id, $user_id,
        $user_id, $user_id
    ]);

    $conversations = $stmt->fetchAll();

    // Calcular total de mensajes no leídos
    $total_unread = array_sum(array_column($conversations, 'unread_count'));

    echo json_encode([
        'success' => true,
        'conversations' => $conversations,
        'total_unread' => $total_unread
    ]);

} catch (Exception $e) {
    error_log("ERROR - get_conversations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
