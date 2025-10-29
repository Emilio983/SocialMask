<?php
/**
 * API: CREATE REPORT
 * Report a user, post, or community
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

$report_type = $data['report_type'] ?? null; // spam, harassment, inappropriate_content, scam, fake_account, other
$description = $data['description'] ?? null;
$reported_user_id = $data['reported_user_id'] ?? null;
$reported_post_id = $data['reported_post_id'] ?? null;
$reported_community_id = $data['reported_community_id'] ?? null;

// Validate
$valid_types = ['spam', 'harassment', 'inappropriate_content', 'scam', 'fake_account', 'other'];
if (!$report_type || !in_array($report_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid report type']);
    exit;
}

if (!$description || strlen(trim($description)) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Description must be at least 10 characters']);
    exit;
}

if (!$reported_user_id && !$reported_post_id && !$reported_community_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Must specify what you are reporting']);
    exit;
}

// Rate limit: Max 3 reports per day per user
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM user_reports
    WHERE reporter_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute([$user_id]);
$report_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($report_count >= 3) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Report limit reached. You can only submit 3 reports per day.'
    ]);
    exit;
}

try {
    // Verify reported content exists
    if ($reported_user_id) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $stmt->execute([$reported_user_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }
    }

    if ($reported_post_id) {
        $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
        $stmt->execute([$reported_post_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Post not found']);
            exit;
        }
    }

    if ($reported_community_id) {
        $stmt = $pdo->prepare("SELECT id FROM communities WHERE id = ?");
        $stmt->execute([$reported_community_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Community not found']);
            exit;
        }
    }

    // Create report
    $stmt = $pdo->prepare("
        INSERT INTO user_reports (
            reporter_id,
            reported_user_id,
            reported_post_id,
            reported_community_id,
            report_type,
            description,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    $stmt->execute([
        $user_id,
        $reported_user_id,
        $reported_post_id,
        $reported_community_id,
        $report_type,
        trim($description)
    ]);

    $report_id = $pdo->lastInsertId();

    // Notify admins
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($admins)) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, 'report', 'New Report', ?, ?)
        ");

        $message = "New {$report_type} report received";
        $notification_data = json_encode(['report_id' => $report_id]);

        foreach ($admins as $admin_id) {
            try {
                $stmt->execute([$admin_id, $message, $notification_data]);
            } catch (Exception $e) {
                error_log("Error notifying admin: " . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully. Our team will review it shortly.',
        'report_id' => $report_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to submit report'
    ]);
}
?>
