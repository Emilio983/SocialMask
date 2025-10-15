<?php
/**
 * GET LESSONS API
 * Retorna lista de lecciones publicadas
 * Accesible sin autenticación
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/connection.php';

session_start();

$user_id = $_SESSION['user_id'] ?? null;

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
    // Get all published lessons
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
            l.`order`,
            (SELECT COUNT(*) FROM learn_lesson_content WHERE lesson_id = l.id) as content_count,
            (SELECT COUNT(*) FROM learn_lesson_content WHERE lesson_id = l.id AND content_type = 'question') as question_count,
            u.username as created_by_username
        FROM learn_lessons l
        LEFT JOIN users u ON l.created_by = u.user_id
        WHERE l.status = 'published'
        ORDER BY l.`order` ASC, l.created_at DESC
    ");
    $stmt->execute();
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add progress information if user is authenticated or has session
    foreach ($lessons as &$lesson) {
        $progress = 0;
        $completed = false;

        if ($user_id) {
            $stmt = $pdo->prepare("
                SELECT progress_percentage, completed
                FROM learn_user_progress
                WHERE user_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$user_id, $lesson['id']]);
            $progressData = $stmt->fetch();

            if ($progressData) {
                $progress = intval($progressData['progress_percentage']);
                $completed = (bool)$progressData['completed'];
            }
        } elseif (isset($session_id)) {
            $stmt = $pdo->prepare("
                SELECT progress_percentage, completed
                FROM learn_user_progress
                WHERE session_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$session_id, $lesson['id']]);
            $progressData = $stmt->fetch();

            if ($progressData) {
                $progress = intval($progressData['progress_percentage']);
                $completed = (bool)$progressData['completed'];
            }
        }

        $lesson['progress'] = $progress;
        $lesson['completed'] = $completed;
        $lesson['content_count'] = intval($lesson['content_count']);
        $lesson['question_count'] = intval($lesson['question_count']);
        $lesson['estimated_time'] = intval($lesson['estimated_time']);
        $lesson['sphe_reward'] = floatval($lesson['sphe_reward']);
    }

    echo json_encode([
        'success' => true,
        'lessons' => $lessons
    ]);

} catch (PDOException $e) {
    error_log("Get lessons error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch lessons']);
}
?>
