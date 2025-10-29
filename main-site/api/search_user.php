<?php
/**
 * SEARCH USER BY @USERNAME
 * Búsqueda de usuarios por unique_username para enviar mensajes
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

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

// Remove @ if present
$query = ltrim($query, '@');

try {
    // Search by unique_username or username
    $stmt = $pdo->prepare("
        SELECT
            user_id,
            username,
            unique_username,
            profile_image,
            bio,
            membership_plan,
            verified,
            (SELECT COUNT(*) FROM user_follows WHERE followed_id = users.user_id) as followers_count
        FROM users
        WHERE (unique_username LIKE ? OR username LIKE ?)
        AND status = 'active'
        LIMIT 20
    ");

    $search_param = $query . '%';
    $stmt->execute([$search_param, $search_param]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format results
    $results = array_map(function($user) {
        return [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'unique_username' => $user['unique_username'],
            'display_name' => '@' . $user['unique_username'],
            'profile_image' => $user['profile_image'],
            'bio' => $user['bio'],
            'membership_plan' => $user['membership_plan'],
            'verified' => (bool)$user['verified'],
            'followers_count' => intval($user['followers_count'])
        ];
    }, $users);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'count' => count($results),
        'users' => $results
    ]);

} catch (PDOException $e) {
    error_log("Search user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
?>
