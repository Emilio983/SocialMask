<?php
/**
 * GET SETTINGS API
 * Obtiene configuración de la plataforma (solo admins)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/connection.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

try {
    // Obtener parámetros opcionales
    $category = isset($_GET['category']) ? $_GET['category'] : null;

    // Query base
    $query = "SELECT * FROM platform_settings";
    $params = [];

    if ($category) {
        $query .= " WHERE category = ?";
        $params[] = $category;
    }

    $query .= " ORDER BY category, setting_key";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar por categoría
    $organized = [];
    foreach ($settings as $setting) {
        $cat = $setting['category'];
        if (!isset($organized[$cat])) {
            $organized[$cat] = [];
        }

        // Convertir valor según tipo
        $value = $setting['setting_value'];
        switch ($setting['setting_type']) {
            case 'number':
                $value = floatval($value);
                break;
            case 'boolean':
                $value = ($value === 'true' || $value === '1');
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
        }

        $organized[$cat][] = [
            'id' => $setting['id'],
            'key' => $setting['setting_key'],
            'value' => $value,
            'type' => $setting['setting_type'],
            'description' => $setting['description'],
            'is_editable' => (bool)$setting['is_editable'],
            'updated_at' => $setting['updated_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'settings' => $organized,
        'settings_count' => count($settings)
    ]);

} catch (PDOException $e) {
    error_log("Get settings error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
