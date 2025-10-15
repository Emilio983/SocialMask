<?php
/**
 * SUBMIT ANSWER API
 * Registra la respuesta del usuario a una pregunta
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
$content_id = isset($data['content_id']) ? filter_var($data['content_id'], FILTER_VALIDATE_INT) : null;
$user_answer = isset($data['answer']) ? trim($data['answer']) : null;
$is_correct = isset($data['is_correct']) ? (bool)$data['is_correct'] : false;

// Validate required fields
if (!$lesson_id || !$content_id || !$user_answer) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate answer is A, B, C, or D
if (!in_array($user_answer, ['A', 'B', 'C', 'D'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid answer format']);
    exit;
}

try {
    // Check if answer already exists
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT id, attempts FROM learn_user_answers
            WHERE user_id = ? AND content_id = ?
            ORDER BY answered_at DESC LIMIT 1
        ");
        $stmt->execute([$user_id, $content_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, attempts FROM learn_user_answers
            WHERE session_id = ? AND content_id = ?
            ORDER BY answered_at DESC LIMIT 1
        ");
        $stmt->execute([$session_id, $content_id]);
    }

    $existingAnswer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAnswer && !$is_correct) {
        // Update attempts
        $stmt = $pdo->prepare("
            UPDATE learn_user_answers
            SET attempts = attempts + 1, answered_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$existingAnswer['id']]);
    } else {
        // Insert new answer
        $stmt = $pdo->prepare("
            INSERT INTO learn_user_answers
            (user_id, session_id, lesson_id, content_id, user_answer, is_correct, attempts)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$user_id, $session_id ?? null, $lesson_id, $content_id, $user_answer, $is_correct]);
    }

    // Update progress
    if ($is_correct) {
        // Calculate progress percentage
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_questions
            FROM learn_lesson_content
            WHERE lesson_id = ? AND content_type = 'question'
        ");
        $stmt->execute([$lesson_id]);
        $totalQuestions = $stmt->fetch()['total_questions'];

        if ($user_id) {
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT content_id) as correct_count
                FROM learn_user_answers
                WHERE user_id = ? AND lesson_id = ? AND is_correct = TRUE
            ");
            $stmt->execute([$user_id, $lesson_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT content_id) as correct_count
                FROM learn_user_answers
                WHERE session_id = ? AND lesson_id = ? AND is_correct = TRUE
            ");
            $stmt->execute([$session_id, $lesson_id]);
        }

        $correctCount = $stmt->fetch()['correct_count'];
        $progressPercentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;

        // Update or create progress record
        if ($user_id) {
            $stmt = $pdo->prepare("
                INSERT INTO learn_user_progress (user_id, lesson_id, progress_percentage)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    progress_percentage = ?,
                    updated_at = NOW()
            ");
            $stmt->execute([$user_id, $lesson_id, $progressPercentage, $progressPercentage]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO learn_user_progress (session_id, lesson_id, progress_percentage)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    progress_percentage = ?,
                    updated_at = NOW()
            ");
            $stmt->execute([$session_id, $lesson_id, $progressPercentage, $progressPercentage]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $is_correct ? 'Correct answer' : 'Incorrect answer'
    ]);

} catch (PDOException $e) {
    error_log("Submit answer error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to submit answer']);
}
?>
