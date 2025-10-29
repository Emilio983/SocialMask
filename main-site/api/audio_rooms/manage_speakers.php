<?php
/**
 * API: MANAGE AUDIO ROOM SPEAKERS
 * Invite speakers, promote listeners, remove speakers
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

$room_id = $data['room_id'] ?? null;
$target_user_id = $data['target_user_id'] ?? null;
$action = $data['action'] ?? null; // 'invite', 'promote', 'demote', 'remove'

if (!$room_id || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    // Verify user is host of the room
    $stmt = $pdo->prepare("
        SELECT role FROM audio_room_participants
        WHERE room_id = ? AND user_id = ? AND left_at IS NULL
    ");
    $stmt->execute([$room_id, $user_id]);
    $user_role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_role || $user_role['role'] !== 'host') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only host can manage speakers']);
        exit;
    }

    // Get room info
    $stmt = $pdo->prepare("SELECT * FROM audio_rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Room not found']);
        exit;
    }

    switch ($action) {
        case 'invite':
            // Invite a user to be speaker
            if (!$target_user_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Target user ID required']);
                exit;
            }

            // Check if user is community member
            $stmt = $pdo->prepare("
                SELECT 1 FROM community_members
                WHERE community_id = ? AND user_id = ?
            ");
            $stmt->execute([$room['community_id'], $target_user_id]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User must be community member']);
                exit;
            }

            // Check max speakers
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM audio_room_participants
                WHERE room_id = ? AND role = 'speaker' AND left_at IS NULL
            ");
            $stmt->execute([$room_id]);
            $speaker_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($speaker_count >= $room['max_speakers']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Max speakers limit reached']);
                exit;
            }

            // Create invitation
            $stmt = $pdo->prepare("
                INSERT INTO audio_room_invitations (room_id, invited_user_id, invited_by, status)
                VALUES (?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE status = 'pending', invited_at = NOW()
            ");
            $stmt->execute([$room_id, $target_user_id, $user_id]);

            // Notify user
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, data)
                VALUES (?, 'audio_room', 'Speaker Invitation', ?, ?)
            ");
            $message = "You've been invited to speak in: {$room['title']}";
            $notification_data = json_encode(['room_id' => $room_id, 'action' => 'speaker_invite']);
            $stmt->execute([$target_user_id, $message, $notification_data]);

            $response_message = 'Speaker invitation sent';
            break;

        case 'promote':
            // Promote listener to speaker
            if (!$target_user_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Target user ID required']);
                exit;
            }

            // Check if user is in room as listener
            $stmt = $pdo->prepare("
                SELECT role FROM audio_room_participants
                WHERE room_id = ? AND user_id = ? AND left_at IS NULL
            ");
            $stmt->execute([$room_id, $target_user_id]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User not in room']);
                exit;
            }

            if ($target['role'] === 'speaker') {
                echo json_encode(['success' => true, 'message' => 'User is already a speaker']);
                exit;
            }

            // Check max speakers
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM audio_room_participants
                WHERE room_id = ? AND role = 'speaker' AND left_at IS NULL
            ");
            $stmt->execute([$room_id]);
            $speaker_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($speaker_count >= $room['max_speakers']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Max speakers limit reached']);
                exit;
            }

            // Promote to speaker
            $stmt = $pdo->prepare("
                UPDATE audio_room_participants
                SET role = 'speaker'
                WHERE room_id = ? AND user_id = ?
            ");
            $stmt->execute([$room_id, $target_user_id]);

            // Notify
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                VALUES (?, 'audio_room', 'Promoted to Speaker', ?)
            ");
            $message = "You've been promoted to speaker in: {$room['title']}";
            $stmt->execute([$target_user_id, $message]);

            $response_message = 'User promoted to speaker';
            break;

        case 'demote':
            // Demote speaker to listener
            if (!$target_user_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Target user ID required']);
                exit;
            }

            // Update role
            $stmt = $pdo->prepare("
                UPDATE audio_room_participants
                SET role = 'listener'
                WHERE room_id = ? AND user_id = ? AND role = 'speaker'
            ");
            $stmt->execute([$room_id, $target_user_id]);

            $response_message = 'Speaker demoted to listener';
            break;

        case 'remove':
            // Remove participant from room
            if (!$target_user_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Target user ID required']);
                exit;
            }

            // Mark as left
            $stmt = $pdo->prepare("
                UPDATE audio_room_participants
                SET left_at = NOW()
                WHERE room_id = ? AND user_id = ?
            ");
            $stmt->execute([$room_id, $target_user_id]);

            // Update current listeners count
            $stmt = $pdo->prepare("
                UPDATE audio_rooms
                SET current_listeners = (
                    SELECT COUNT(*) FROM audio_room_participants
                    WHERE room_id = ? AND left_at IS NULL
                )
                WHERE id = ?
            ");
            $stmt->execute([$room_id, $room_id]);

            $response_message = 'User removed from room';
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $response_message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to perform action'
    ]);
}
?>
