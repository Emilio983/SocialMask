<?php
// ============================================
// TOGGLE MONETIZATION API
// Activa/desactiva la monetización con ads en el perfil del usuario
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
    $ads_enabled = $data['ads_enabled'] ?? false;
    $ads_frequency = $data['ads_frequency'] ?? 'low';
    $marketing_posts_enabled = $data['marketing_posts_enabled'] ?? true;

    // Actualizar configuración de monetización
    $stmt = $pdo->prepare("
        INSERT INTO user_monetization_settings
            (user_id, ads_enabled, ads_frequency, marketing_posts_enabled)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            ads_enabled = VALUES(ads_enabled),
            ads_frequency = VALUES(ads_frequency),
            marketing_posts_enabled = VALUES(marketing_posts_enabled)
    ");

    $stmt->execute([
        $user_id,
        $ads_enabled,
        $ads_frequency,
        $marketing_posts_enabled
    ]);

    // Obtener configuración actualizada
    $stmt = $pdo->prepare("SELECT * FROM user_monetization_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $monetization = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Monetization settings updated successfully',
        'monetization' => $monetization
    ]);

} catch (Exception $e) {
    error_log("ERROR - toggle_monetization.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating monetization settings'
    ]);
}
?>
