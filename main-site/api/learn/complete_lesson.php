<?php
/**
 * COMPLETE LESSON API
 * Marca una lección como completada y otorga recompensa SPHE
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../../config/connection.php';

session_start();

$user_id = $_SESSION['user_id'] ?? null;

// Get session ID for non-authenticated users
if (!$user_id) {
    $session_id = $_COOKIE['learn_session'] ?? null;
    if (!$session_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No session']);
        exit;
    }
}

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$lesson_id = isset($data['lesson_id']) ? filter_var($data['lesson_id'], FILTER_VALIDATE_INT) : null;

if (!$lesson_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Lesson ID required']);
    exit;
}

try {
    // Get lesson data
    $stmt = $pdo->prepare("SELECT sphe_reward FROM learn_lessons WHERE id = ?");
    $stmt->execute([$lesson_id]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lesson not found']);
        exit;
    }

    // Check if already completed and claimed reward
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT completed, reward_claimed
            FROM learn_user_progress
            WHERE user_id = ? AND lesson_id = ?
        ");
        $stmt->execute([$user_id, $lesson_id]);
        $progress = $stmt->fetch();

        if ($progress && $progress['completed'] && $progress['reward_claimed']) {
            echo json_encode([
                'success' => true,
                'message' => 'Lesson already completed',
                'reward_given' => false
            ]);
            exit;
        }
    }

    // Mark as completed
    if ($user_id) {
        $stmt = $pdo->prepare("
            INSERT INTO learn_user_progress
            (user_id, lesson_id, progress_percentage, completed, completed_at, reward_claimed)
            VALUES (?, ?, 100, TRUE, NOW(), TRUE)
            ON DUPLICATE KEY UPDATE
                progress_percentage = 100,
                completed = TRUE,
                completed_at = NOW(),
                reward_claimed = TRUE,
                updated_at = NOW()
        ");
        $stmt->execute([$user_id, $lesson_id]);

        // Give SPHE reward if user is logged in and lesson has reward
        if ($lesson['sphe_reward'] > 0) {
            // Update user balance
            $stmt = $pdo->prepare("
                UPDATE users
                SET sphe_balance = sphe_balance + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$lesson['sphe_reward'], $user_id]);

            // Record transaction
            $stmt = $pdo->prepare("
                INSERT INTO sphe_transactions
                (to_user_id, transaction_type, amount, description, reference_type, status)
                VALUES (?, 'reward', ?, ?, 'learn', 'completed')
            ");
            $stmt->execute([
                $user_id,
                $lesson['sphe_reward'],
                "Recompensa por completar lección ID: {$lesson_id}"
            ]);

            $rewardGiven = true;
        } else {
            $rewardGiven = false;
        }
    } else {
        // For non-logged users, just mark as completed
        $stmt = $pdo->prepare("
            INSERT INTO learn_user_progress
            (session_id, lesson_id, progress_percentage, completed, completed_at)
            VALUES (?, ?, 100, TRUE, NOW())
            ON DUPLICATE KEY UPDATE
                progress_percentage = 100,
                completed = TRUE,
                completed_at = NOW(),
                updated_at = NOW()
        ");
        $stmt->execute([$session_id, $lesson_id]);
        $rewardGiven = false;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Lesson completed successfully',
        'reward_given' => $rewardGiven,
        'reward_amount' => $lesson['sphe_reward']
    ]);

} catch (PDOException $e) {
    error_log("Complete lesson error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to complete lesson']);
}
?>
