<?php
// ============================================
// GET PROFILE API
// Obtiene información completa del perfil de un usuario
// ============================================

require_once __DIR__ . '/../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Obtener parámetros
    $profile_username = $_GET['username'] ?? null;
    $profile_user_id = $_GET['user_id'] ?? null;
    $viewer_user_id = $_SESSION['user_id'] ?? null;

    // Validar que se proporcione username o user_id
    if (!$profile_username && !$profile_user_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username or user_id is required'
        ]);
        exit;
    }

    // Construir query base
    $query = "SELECT
        u.user_id,
        u.username,
        u.email,
        u.wallet_address,
        u.profile_image,
        u.cover_image,
        u.bio,
        u.sphe_balance,
        u.reputation_points,
        u.reputation_level,
        u.total_contributions,
        u.is_verified,
        u.membership_plan,
        u.status,
        u.created_at,
        u.last_login,
        (SELECT COUNT(*) FROM interactions WHERE target_type = 'user' AND target_id = u.user_id AND interaction_type = 'follow') as followers_count,
        (SELECT COUNT(*) FROM interactions WHERE user_id = u.user_id AND target_type = 'user' AND interaction_type = 'follow') as following_count,
        (SELECT COUNT(*) FROM posts WHERE user_id = u.user_id AND deleted_at IS NULL) as total_posts
    FROM users u
    WHERE ";

    // Agregar condición según parámetro
    if ($profile_username) {
        $query .= "u.username = ?";
        $param = $profile_username;
    } else {
        $query .= "u.user_id = ?";
        $param = $profile_user_id;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute([$param]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }

    // Verificar si el viewer es el dueño del perfil
    $is_own_profile = ($viewer_user_id && $viewer_user_id == $user['user_id']);

    // Verificar si el viewer sigue a este usuario
    $is_following = false;

    if ($viewer_user_id && !$is_own_profile) {
        // Verificar si sigue
        $stmt = $pdo->prepare("SELECT id FROM interactions WHERE user_id = ? AND target_type = 'user' AND target_id = ? AND interaction_type = 'follow'");
        $stmt->execute([$viewer_user_id, $user['user_id']]);
        $is_following = (bool)$stmt->fetch();
    }

    // Obtener posts del usuario (últimos 20)
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            c.name as community_name,
            c.slug as community_slug,
            c.logo as community_logo
        FROM posts p
        LEFT JOIN communities c ON p.community_id = c.id
        WHERE p.user_id = ? AND p.deleted_at IS NULL
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user['user_id']]);
    $posts = $stmt->fetchAll();

    // Preparar respuesta
    $response = [
        'success' => true,
        'profile' => [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'wallet_address' => $user['wallet_address'],
            'profile_image' => $user['profile_image'] ?? '/assets/images/default-avatar.png',
            'cover_image' => $user['cover_image'] ?? '/assets/images/default-cover.jpg',
            'bio' => $user['bio'] ?? '',
            'stats' => [
                'sphe_balance' => $user['sphe_balance'] ?? 0,
                'reputation_points' => $user['reputation_points'] ?? 0,
                'reputation_level' => $user['reputation_level'] ?? 1,
                'total_posts' => $user['total_posts'] ?? 0,
                'total_contributions' => $user['total_contributions'] ?? 0,
                'followers' => $user['followers_count'] ?? 0,
                'following' => $user['following_count'] ?? 0
            ],
            'membership' => [
                'plan' => $user['membership_plan'] ?? 'free',
                'is_verified' => (bool)$user['is_verified']
            ],
            'status' => $user['status'],
            'member_since' => $user['created_at'],
            'last_active' => $user['last_login']
        ],
        'posts' => $posts,
        'viewer_context' => [
            'is_own_profile' => $is_own_profile,
            'is_following' => $is_following
        ]
    ];

    // Agregar datos privados solo si es el dueño
    if ($is_own_profile) {
        $response['profile']['email'] = $user['email'];
        // Monetization placeholder (puede ser implementado después)
        $response['monetization'] = [
            'ads_enabled' => false,
            'total_ad_views' => 0,
            'total_ad_clicks' => 0,
            'total_ad_earnings' => 0
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("ERROR - get_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching profile'
    ]);
}
?>
