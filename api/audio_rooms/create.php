<?php
/**
 * API: CREATE AUDIO ROOM
 * Create a podcast/audio room in a community
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

$community_id = $data['community_id'] ?? null;
$title = $data['title'] ?? null;
$description = $data['description'] ?? null;
$scheduled_start = $data['scheduled_start'] ?? null;
$start_now = $data['start_now'] ?? false;
$max_speakers = $data['max_speakers'] ?? 10;

// Validate
if (!$community_id || !$title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    // Verify user is member of community
    $stmt = $pdo->prepare("
        SELECT role FROM community_members
        WHERE community_id = ? AND user_id = ?
    ");
    $stmt->execute([$community_id, $user_id]);
    $membership = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membership) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You must be a member to create audio rooms']);
        exit;
    }

    // Only admins/moderators/creators can create audio rooms
    $allowed_roles = ['owner', 'admin', 'moderator'];
    if (!in_array($membership['role'], $allowed_roles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only community moderators can create audio rooms']);
        exit;
    }

    // Set scheduled time
    if ($start_now) {
        $scheduled_time = date('Y-m-d H:i:s');
        $status = 'live';
        $actual_start = date('Y-m-d H:i:s');
    } else {
        $scheduled_time = $scheduled_start ? date('Y-m-d H:i:s', strtotime($scheduled_start)) : date('Y-m-d H:i:s', strtotime('+1 hour'));
        $status = 'scheduled';
        $actual_start = null;
    }

    // Create audio room
    $stmt = $pdo->prepare("
        INSERT INTO audio_rooms (
            community_id,
            creator_id,
            title,
            description,
            status,
            scheduled_start,
            actual_start,
            max_speakers,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $community_id,
        $user_id,
        $title,
        $description,
        $status,
        $scheduled_time,
        $actual_start,
        $max_speakers
    ]);

    $room_id = $pdo->lastInsertId();

    // Add creator as host
    $stmt = $pdo->prepare("
        INSERT INTO audio_room_participants (room_id, user_id, role)
        VALUES (?, ?, 'host')
    ");
    $stmt->execute([$room_id, $user_id]);

    // Notify community members
    if ($start_now) {
        // Get all community members
        $stmt = $pdo->prepare("
            SELECT user_id FROM community_members
            WHERE community_id = ? AND user_id != ?
            LIMIT 100
        ");
        $stmt->execute([$community_id, $user_id]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Create notifications
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, 'audio_room', 'Live Audio Room', ?, ?)
        ");

        $notification_message = "🎙️ {$title} is now live! Join the conversation";
        $notification_data = json_encode(['room_id' => $room_id, 'community_id' => $community_id]);

        foreach ($members as $member_id) {
            $stmt->execute([$member_id, $notification_message, $notification_data]);
        }
    }

    echo json_encode([
        'success' => true,
        'room_id' => $room_id,
        'status' => $status,
        'message' => $start_now ? 'Audio room started!' : 'Audio room scheduled'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create audio room'
    ]);
}
?>
