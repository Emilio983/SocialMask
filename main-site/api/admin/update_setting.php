<?php
/**
 * UPDATE SETTING API
 * Actualiza un valor de configuración (solo admins)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/connection.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

$user_id = $admin_id; // From admin_auth.php

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$setting_key = isset($input['setting_key']) ? trim($input['setting_key']) : null;
$new_value = isset($input['value']) ? $input['value'] : null;

if (!$setting_key || $new_value === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'setting_key and value are required']);
    exit;
}

try {
    // Obtener configuración actual
    $stmt = $pdo->prepare("SELECT * FROM platform_settings WHERE setting_key = ?");
    $stmt->execute([$setting_key]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$setting) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Setting not found']);
        exit;
    }

    // Verificar si es editable
    if (!$setting['is_editable']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'This setting cannot be edited']);
        exit;
    }

    $old_value = $setting['setting_value'];

    // Validar y convertir valor según tipo
    switch ($setting['setting_type']) {
        case 'number':
            if (!is_numeric($new_value)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Value must be a number']);
                exit;
            }
            $new_value = strval(floatval($new_value));

            // Validaciones específicas
            if ($new_value < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Value cannot be negative']);
                exit;
            }
            break;

        case 'boolean':
            $new_value = $new_value ? 'true' : 'false';
            break;

        case 'json':
            if (!is_array($new_value) && !is_object($new_value)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Value must be JSON object or array']);
                exit;
            }
            $new_value = json_encode($new_value);
            break;

        case 'string':
            $new_value = strval($new_value);
            break;
    }

    // Actualizar configuración
    $stmt = $pdo->prepare("
        UPDATE platform_settings
        SET setting_value = ?,
            updated_by = ?,
            updated_at = NOW()
        WHERE setting_key = ?
    ");

    $result = $stmt->execute([$new_value, $user_id, $setting_key]);

    if (!$result) {
        throw new Exception('Failed to update setting');
    }

    // Loggear acción en admin_actions
    $stmt = $pdo->prepare("
        INSERT INTO admin_actions (
            admin_id,
            action_type,
            target_type,
            target_id,
            action_details,
            ip_address,
            created_at
        ) VALUES (?, 'update_setting', 'platform_setting', ?, ?, ?, NOW())
    ");

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $action_details = json_encode([
        'setting_key' => $setting_key,
        'old_value' => $old_value,
        'new_value' => $new_value,
        'description' => "Updated {$setting_key}"
    ]);

    $stmt->execute([
        $user_id,
        $setting['id'],
        $action_details,
        $ip
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Setting updated successfully',
        'setting_key' => $setting_key,
        'old_value' => $old_value,
        'new_value' => $new_value,
        'updated_by' => $user_id
    ]);

} catch (PDOException $e) {
    error_log("Update setting error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
