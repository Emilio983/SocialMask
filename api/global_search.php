<?php
/**
 * GLOBAL SEARCH API
 * Búsqueda universal: Comunidades, Usuarios, Posts
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

// Get search parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'all'; // all, communities, users, posts
$limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 5; // Default 5 per category

if (empty($query)) {
    echo json_encode([
        'success' => true,
        'query' => '',
        'results' => [
            'communities' => [],
            'users' => [],
            'posts' => []
        ]
    ]);
    exit;
}

$search_param = '%' . $query . '%';

try {
    $results = [];

    // ===================================================================
    // SEARCH COMMUNITIES
    // ===================================================================
    if ($type === 'all' || $type === 'communities') {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.name,
                c.slug,
                c.description,
                c.logo_url,
                c.member_count,
                c.post_count,
                c.category,
                c.is_private,
                u.username as owner_username,
                EXISTS(
                    SELECT 1 FROM community_members
                    WHERE community_id = c.id AND user_id = ?
                ) as is_member
            FROM communities c
            LEFT JOIN users u ON c.owner_id = u.user_id
            WHERE (c.name LIKE ? OR c.description LIKE ? OR c.category LIKE ?)
            AND c.deleted_at IS NULL
            AND c.status = 'active'
            ORDER BY c.member_count DESC
            LIMIT ?
        ");
        $stmt->execute([$current_user_id, $search_param, $search_param, $search_param, $limit]);
        $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results['communities'] = array_map(function($c) {
            return [
                'id' => $c['id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'description' => $c['description'],
                'logo_url' => $c['logo_url'],
                'member_count' => intval($c['member_count']),
                'post_count' => intval($c['post_count']),
                'category' => $c['category'],
                'is_private' => (bool)$c['is_private'],
                'owner_username' => $c['owner_username'],
                'is_member' => (bool)$c['is_member']
            ];
        }, $communities);
    }

    // ===================================================================
    // SEARCH USERS
    // ===================================================================
    if ($type === 'all' || $type === 'users') {
        $stmt = $pdo->prepare("
            SELECT
                u.user_id,
                u.username,
                u.unique_username,
                u.profile_image,
                u.bio,
                u.membership_plan,
                u.is_verified,
                u.reputation_points,
                (SELECT COUNT(*) FROM interactions WHERE target_type = 'user' AND target_id = u.user_id AND interaction_type = 'follow') as followers_count,
                EXISTS(
                    SELECT 1 FROM interactions
                    WHERE user_id = ? AND target_type = 'user' AND target_id = u.user_id AND interaction_type = 'follow'
                ) as is_following
            FROM users u
            WHERE (u.username LIKE ? OR u.unique_username LIKE ? OR u.email LIKE ?)
            AND u.account_status = 'active'
            AND u.user_id != ?
            ORDER BY followers_count DESC, u.reputation_points DESC
            LIMIT ?
        ");
        $stmt->execute([$current_user_id, $search_param, $search_param, $search_param, $current_user_id, $limit]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results['users'] = array_map(function($u) {
            return [
                'user_id' => $u['user_id'],
                'username' => $u['username'],
                'unique_username' => $u['unique_username'],
                'display_name' => $u['unique_username'] ? '@' . $u['unique_username'] : '@' . $u['username'],
                'profile_image' => $u['profile_image'],
                'bio' => $u['bio'],
                'membership_plan' => $u['membership_plan'],
                'verified' => (bool)$u['is_verified'],
                'reputation_points' => intval($u['reputation_points']),
                'followers_count' => intval($u['followers_count']),
                'is_following' => (bool)$u['is_following']
            ];
        }, $users);
    }

    // ===================================================================
    // SEARCH POSTS
    // ===================================================================
    if ($type === 'all' || $type === 'posts') {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.content,
                p.post_type,
                p.created_at,
                p.like_count,
                p.comment_count,
                p.share_count,
                u.user_id,
                u.username,
                u.unique_username,
                u.profile_image,
                u.membership_plan,
                c.id as community_id,
                c.name as community_name,
                c.slug as community_slug
            FROM posts p
            INNER JOIN users u ON p.user_id = u.user_id
            LEFT JOIN communities c ON p.community_id = c.id
            WHERE p.content LIKE ?
            AND p.deleted_at IS NULL
            AND u.status = 'active'
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$search_param, $limit]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results['posts'] = array_map(function($p) {
            return [
                'id' => $p['id'],
                'content' => $p['content'],
                'post_type' => $p['post_type'],
                'created_at' => $p['created_at'],
                'like_count' => intval($p['like_count']),
                'comment_count' => intval($p['comment_count']),
                'share_count' => intval($p['share_count']),
                'author' => [
                    'user_id' => $p['user_id'],
                    'username' => $p['username'],
                    'unique_username' => $p['unique_username'],
                    'profile_image' => $p['profile_image'],
                    'membership_plan' => $p['membership_plan']
                ],
                'community' => $p['community_id'] ? [
                    'id' => $p['community_id'],
                    'name' => $p['community_name'],
                    'slug' => $p['community_slug']
                ] : null
            ];
        }, $posts);
    }

    // Calculate total results
    $total_results = 0;
    if (isset($results['communities'])) $total_results += count($results['communities']);
    if (isset($results['users'])) $total_results += count($results['users']);
    if (isset($results['posts'])) $total_results += count($results['posts']);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'type' => $type,
        'total_results' => $total_results,
        'results' => $results,
        'has_more' => [
            'communities' => isset($results['communities']) && count($results['communities']) == $limit,
            'users' => isset($results['users']) && count($results['users']) == $limit,
            'posts' => isset($results['posts']) && count($results['posts']) == $limit
        ]
    ]);

} catch (PDOException $e) {
    error_log("Global search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
?>
