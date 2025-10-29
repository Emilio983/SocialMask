<?php
// ============================================
// UPDATE PROFILE API
// Actualiza información del perfil de usuario
// ============================================

require_once '../config/connection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Obtener datos del request
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data'
        ]);
        exit;
    }

    $action = $data['action'] ?? 'update_basic';

    switch ($action) {
        case 'update_basic':
            // Actualizar información básica
            $bio = $data['bio'] ?? null;
            $location = $data['location'] ?? null;
            $website = $data['website'] ?? null;
            $twitter = $data['twitter_handle'] ?? null;
            $discord = $data['discord_handle'] ?? null;
            $telegram = $data['telegram_handle'] ?? null;

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    bio = ?,
                    location = ?,
                    website = ?,
                    twitter_handle = ?,
                    discord_handle = ?,
                    telegram_handle = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $bio,
                $location,
                $website,
                $twitter,
                $discord,
                $telegram,
                $user_id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
            break;

        case 'update_images':
            // Actualizar imágenes (profile_image o cover_image)
            $image_type = $data['image_type'] ?? null; // 'profile' o 'cover'
            $image_url = $data['image_url'] ?? null;

            if (!$image_type || !$image_url) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'image_type and image_url are required'
                ]);
                exit;
            }

            $column = ($image_type === 'profile') ? 'profile_image' : 'cover_image';

            $stmt = $pdo->prepare("UPDATE users SET $column = ? WHERE user_id = ?");
            $stmt->execute([$image_url, $user_id]);

            echo json_encode([
                'success' => true,
                'message' => ucfirst($image_type) . ' image updated successfully'
            ]);
            break;

        case 'update_social_links':
            // Actualizar links sociales
            $social_links = $data['social_links'] ?? [];

            // Eliminar links anteriores
            $stmt = $pdo->prepare("DELETE FROM user_social_links WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Insertar nuevos links
            $stmt = $pdo->prepare("
                INSERT INTO user_social_links (user_id, platform, label, url, icon, display_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($social_links as $index => $link) {
                $stmt->execute([
                    $user_id,
                    $link['platform'] ?? 'other',
                    $link['label'] ?? '',
                    $link['url'] ?? '',
                    $link['icon'] ?? null,
                    $index
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Social links updated successfully'
            ]);
            break;

        case 'update_privacy':
            // Actualizar configuración de privacidad
            $profile_visibility = $data['profile_visibility'] ?? 'public';
            $allow_messages_from = $data['allow_messages_from'] ?? 'everyone';
            $show_email = $data['show_email'] ?? false;
            $show_wallet = $data['show_wallet'] ?? false;
            $show_followers_count = $data['show_followers_count'] ?? true;
            $show_following_count = $data['show_following_count'] ?? true;
            $show_online_status = $data['show_online_status'] ?? true;
            $allow_tags = $data['allow_tags'] ?? true;

            $stmt = $pdo->prepare("
                INSERT INTO user_privacy_settings
                    (user_id, profile_visibility, allow_messages_from, show_email, show_wallet,
                     show_followers_count, show_following_count, show_online_status, allow_tags)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    profile_visibility = VALUES(profile_visibility),
                    allow_messages_from = VALUES(allow_messages_from),
                    show_email = VALUES(show_email),
                    show_wallet = VALUES(show_wallet),
                    show_followers_count = VALUES(show_followers_count),
                    show_following_count = VALUES(show_following_count),
                    show_online_status = VALUES(show_online_status),
                    allow_tags = VALUES(allow_tags)
            ");

            $stmt->execute([
                $user_id,
                $profile_visibility,
                $allow_messages_from,
                $show_email,
                $show_wallet,
                $show_followers_count,
                $show_following_count,
                $show_online_status,
                $allow_tags
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Privacy settings updated successfully'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("ERROR - update_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating profile'
    ]);
}
?>
