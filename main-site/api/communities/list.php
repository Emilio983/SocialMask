<?php
// ============================================
// LIST COMMUNITIES API
// GET /api/communities/list.php
// ============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../config/connection.php';

    // Get current user ID (if logged in)
    $current_user_id = $_SESSION['user_id'] ?? null;

    // Get query parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
    $filter = $_GET['filter'] ?? 'all'; // all|sponsored|my_communities
    $search = $_GET['search'] ?? '';

    $offset = ($page - 1) * $limit;

    // Build query
    $where_clauses = ["c.status = 'active'"];
    $params = [];

    // Filter by type
    if ($filter === 'sponsored') {
        $where_clauses[] = "1=0"; // No hay comunidades patrocinadas por ahora
    } elseif ($filter === 'my_communities' && $current_user_id) {
        $where_clauses[] = "cm.user_id = ?";
        $params[] = $current_user_id;
    }

    // Search filter
    if (!empty($search)) {
        $where_clauses[] = "(c.name LIKE ? OR c.description LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Get total count
    $count_sql = "
        SELECT COUNT(DISTINCT c.id) as total
        FROM communities c
        LEFT JOIN community_members cm ON c.id = cm.community_id
        WHERE $where_sql
    ";

    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];

    // Get communities
    $sql = "
        SELECT
            c.id,
            c.name,
            c.slug,
            c.description,
            c.logo as logo_url,
            c.banner as banner_url,
            c.creator_id as owner_id,
            0 as is_sponsored,
            c.is_private,
            c.membership_fee_sphe as entry_fee_sphe,
            c.member_count,
            c.total_posts as post_count,
            c.created_at,
            u.username as owner_username,
            " . ($current_user_id ?
                "(SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND user_id = ?) as is_member"
                : "0 as is_member"
            ) . "
        FROM communities c
        LEFT JOIN users u ON c.creator_id = u.user_id
        LEFT JOIN community_members cm ON c.id = cm.community_id
        WHERE $where_sql
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $execute_params = [];
    if ($current_user_id) {
        $execute_params[] = $current_user_id;
    }
    $execute_params = array_merge($execute_params, $params);
    $execute_params[] = $limit;
    $execute_params[] = $offset;

    $stmt->execute($execute_params);
    $communities = $stmt->fetchAll();

    // Format response
    $formatted_communities = array_map(function($community) {
        return [
            'id' => (int)$community['id'],
            'name' => $community['name'],
            'slug' => $community['slug'],
            'description' => $community['description'],
            'logo_url' => $community['logo_url'],
            'banner_url' => $community['banner_url'],
            'owner_id' => (int)$community['owner_id'],
            'owner_username' => $community['owner_username'],
            'is_sponsored' => (bool)$community['is_sponsored'],
            'is_private' => (bool)$community['is_private'],
            'entry_fee_sphe' => (float)$community['entry_fee_sphe'],
            'member_count' => (int)$community['member_count'],
            'post_count' => (int)$community['post_count'],
            'is_member' => (bool)$community['is_member'],
            'created_at' => $community['created_at']
        ];
    }, $communities);

    echo json_encode([
        'success' => true,
        'communities' => $formatted_communities,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_communities' => (int)$total,
            'per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    error_log("ERROR - communities/list.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>