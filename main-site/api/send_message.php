<?php
// ============================================
// SEND MESSAGE API
// Envía un mensaje privado a otro usuario
// ============================================

require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $sender_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);

    $receiver_id = $data['receiver_id'] ?? null;
    $content = $data['content'] ?? '';
    $message_type = $data['message_type'] ?? 'text';
    $reply_to_message_id = $data['reply_to_message_id'] ?? null;

    if (!$receiver_id || empty(trim($content))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'receiver_id and content are required']);
        exit;
    }

    if ($sender_id == $receiver_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot send message to yourself']);
        exit;
    }

    // Verificar si alguno bloqueó al otro
    $stmt = $pdo->prepare("SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
    $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
    if ($stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot send message to this user']);
        exit;
    }

    // Verificar configuración de privacidad del receptor
    $stmt = $pdo->prepare("SELECT allow_messages_from FROM user_privacy_settings WHERE user_id = ?");
    $stmt->execute([$receiver_id]);
    $privacy = $stmt->fetch();

    if ($privacy && $privacy['allow_messages_from'] === 'nobody') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This user is not accepting messages']);
        exit;
    }

    if ($privacy && $privacy['allow_messages_from'] === 'followers_only') {
        // Verificar si el sender sigue al receiver
        $stmt = $pdo->prepare("SELECT id FROM user_followers WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$sender_id, $receiver_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'This user only accepts messages from followers']);
            exit;
        }
    }

    // Obtener o crear conversación (user1_id siempre es el ID menor)
    $user1_id = min($sender_id, $receiver_id);
    $user2_id = max($sender_id, $receiver_id);

    $stmt = $pdo->prepare("
        INSERT INTO conversations (user1_id, user2_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
    ");
    $stmt->execute([$user1_id, $user2_id]);
    $conversation_id = $pdo->lastInsertId();

    // Insertar mensaje
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, receiver_id, message_type, content, reply_to_message_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$conversation_id, $sender_id, $receiver_id, $message_type, $content, $reply_to_message_id]);
    $message_id = $pdo->lastInsertId();

    // Obtener mensaje creado
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => $message
    ]);

} catch (Exception $e) {
    error_log("ERROR - send_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while sending message']);
}
?>
