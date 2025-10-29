<?php
/**
 * GET LESSON API
 * Retorna una lección específica con todo su contenido
 * Accesible sin autenticación
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/connection.php';

session_start();

$user_id = $_SESSION['user_id'] ?? null;
$lesson_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$lesson_id || $lesson_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid lesson ID required']);
    exit;
}

// Get session ID for non-authenticated users
if (!$user_id) {
    if (!isset($_COOKIE['learn_session'])) {
        $session_id = bin2hex(random_bytes(16));
        // Set secure cookie with HttpOnly, Secure (if HTTPS), and SameSite
        setcookie('learn_session', $session_id, [
            'expires' => time() + (86400 * 365), // 1 year
            'path' => '/',
            'domain' => '', // Current domain
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only if available
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Lax' // CSRF protection
        ]);
    } else {
        $session_id = $_COOKIE['learn_session'];
    }
}

try {
    // Get lesson data
    $stmt = $pdo->prepare("
        SELECT
            l.id,
            l.title,
            l.description,
            l.summary,
            l.image_url,
            l.difficulty,
            l.estimated_time,
            l.sphe_reward,
            u.username as created_by_username
        FROM learn_lessons l
        LEFT JOIN users u ON l.created_by = u.user_id
        WHERE l.id = ? AND l.status = 'published'
    ");
    $stmt->execute([$lesson_id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Lesson not found']);
        exit;
    }

    // Get lesson content (text, images, questions)
    $stmt = $pdo->prepare("
        SELECT
            id,
            content_type,
            `order`,
            content_text,
            image_url,
            question_text,
            option_a,
            option_b,
            option_c,
            option_d,
            correct_answer,
            explanation
        FROM learn_lesson_content
        WHERE lesson_id = ?
        ORDER BY `order` ASC
    ");
    $stmt->execute([$lesson_id]);
    $content = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user progress
    $progress = null;
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT progress_percentage, completed, completed_at, reward_claimed
            FROM learn_user_progress
            WHERE user_id = ? AND lesson_id = ?
        ");
        $stmt->execute([$user_id, $lesson_id]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($session_id)) {
        $stmt = $pdo->prepare("
            SELECT progress_percentage, completed, completed_at
            FROM learn_user_progress
            WHERE session_id = ? AND lesson_id = ?
        ");
        $stmt->execute([$session_id, $lesson_id]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'lesson' => [
            'id' => $lesson['id'],
            'title' => $lesson['title'],
            'description' => $lesson['description'],
            'summary' => $lesson['summary'],
            'image_url' => $lesson['image_url'],
            'difficulty' => $lesson['difficulty'],
            'estimated_time' => intval($lesson['estimated_time']),
            'sphe_reward' => floatval($lesson['sphe_reward']),
            'created_by' => $lesson['created_by_username']
        ],
        'content' => array_map(function($c) {
            return [
                'id' => $c['id'],
                'content_type' => $c['content_type'],
                'order' => intval($c['order']),
                'content_text' => $c['content_text'],
                'image_url' => $c['image_url'],
                'question_text' => $c['question_text'],
                'option_a' => $c['option_a'],
                'option_b' => $c['option_b'],
                'option_c' => $c['option_c'],
                'option_d' => $c['option_d'],
                'correct_answer' => $c['correct_answer'],
                'explanation' => $c['explanation']
            ];
        }, $content),
        'progress' => $progress
    ]);

} catch (PDOException $e) {
    error_log("Get lesson error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch lesson']);
}
?>
