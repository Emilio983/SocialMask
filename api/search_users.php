<?php
/**
 * SEARCH USERS API
 * Búsqueda de usuarios para mensajes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../config/connection.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 20) : 10;

if (empty($query)) {
    echo json_encode([
        'success' => true,
        'users' => []
    ]);
    exit;
}

$search_param = '%' . $query . '%';

try {
    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.username,
            u.profile_image,
            u.bio,
            u.membership_plan,
            u.is_verified,
            (SELECT COUNT(*) FROM interactions WHERE target_type = 'user' AND target_id = u.user_id AND interaction_type = 'follow') as followers_count
        FROM users u
        WHERE (u.username LIKE ? OR u.email LIKE ?)
        AND u.status = 'active'
        AND u.user_id != ?
        ORDER BY followers_count DESC
        LIMIT ?
    ");
    $stmt->execute([$search_param, $search_param, $current_user_id, $limit]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = array_map(function($u) {
        return [
            'user_id' => $u['user_id'],
            'username' => $u['username'],
            'profile_image' => $u['profile_image'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($u['username']) . '&size=40&background=3B82F6&color=fff',
            'bio' => $u['bio'],
            'membership_plan' => $u['membership_plan'],
            'verified' => (bool)$u['is_verified'],
            'followers_count' => intval($u['followers_count'])
        ];
    }, $users);

    echo json_encode([
        'success' => true,
        'users' => $results
    ]);

} catch (PDOException $e) {
    error_log("Search users error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
?>
