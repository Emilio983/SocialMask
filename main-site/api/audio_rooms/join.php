<?php
/**
 * API: JOIN AUDIO ROOM
 * Join an audio room as listener (can be promoted to speaker)
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

if (!$room_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Room ID required']);
    exit;
}

try {
    // Get room info
    $stmt = $pdo->prepare("
        SELECT ar.*, c.name as community_name
        FROM audio_rooms ar
        INNER JOIN communities c ON ar.community_id = c.id
        WHERE ar.id = ?
    ");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Room not found']);
        exit;
    }

    // Check room status
    if ($room['status'] !== 'live') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Room is not live yet']);
        exit;
    }

    // Verify user is member of community
    $stmt = $pdo->prepare("
        SELECT 1 FROM community_members
        WHERE community_id = ? AND user_id = ?
    ");
    $stmt->execute([$room['community_id'], $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Must be community member to join']);
        exit;
    }

    // Check if already in room
    $stmt = $pdo->prepare("
        SELECT role FROM audio_room_participants
        WHERE room_id = ? AND user_id = ? AND left_at IS NULL
    ");
    $stmt->execute([$room_id, $user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            'success' => true,
            'message' => 'Already in room',
            'role' => $existing['role']
        ]);
        exit;
    }

    // Join as listener
    $stmt = $pdo->prepare("
        INSERT INTO audio_room_participants (room_id, user_id, role, joined_at)
        VALUES (?, ?, 'listener', NOW())
    ");
    $stmt->execute([$room_id, $user_id]);

    // Update current listeners count
    $stmt = $pdo->prepare("
        UPDATE audio_rooms
        SET current_listeners = (
            SELECT COUNT(*) FROM audio_room_participants
            WHERE room_id = ? AND left_at IS NULL
        ),
        total_listeners_peak = GREATEST(
            total_listeners_peak,
            (SELECT COUNT(*) FROM audio_room_participants WHERE room_id = ? AND left_at IS NULL)
        )
        WHERE id = ?
    ");
    $stmt->execute([$room_id, $room_id, $room_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Joined room as listener',
        'role' => 'listener',
        'room' => [
            'id' => $room['id'],
            'title' => $room['title'],
            'community_name' => $room['community_name']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to join room'
    ]);
}
?>
