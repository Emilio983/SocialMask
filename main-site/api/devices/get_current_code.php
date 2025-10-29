<?php
/**
 * API: Obtener código de seguridad actual
 * GET /api/devices/get_current_code.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';

requireAuth();

try {
    $userId = $_SESSION['user_id'];
    
    // Función para generar código seguro (sin 0,O,1,I,L)
    function generateSecurityCode(): string {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
    
    // Buscar código válido existente
    $stmt = $pdo->prepare('
        SELECT code, UNIX_TIMESTAMP(expires_at) as expires_timestamp,
               TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining
        FROM device_security_codes
        WHERE user_id = ? AND expires_at > NOW() AND used = FALSE
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si existe código válido, retornarlo
    if ($existing && $existing['seconds_remaining'] > 0) {
        echo json_encode([
            'success' => true,
            'code' => $existing['code'],
            'expires_at' => $existing['expires_timestamp'],
            'seconds_remaining' => (int)$existing['seconds_remaining']
        ]);
        exit;
    }
    
    // Generar nuevo código (válido 10 minutos)
    $newCode = generateSecurityCode();
    $stmt = $pdo->prepare('
        INSERT INTO device_security_codes (user_id, code, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
    ');
    $stmt->execute([$userId, $newCode]);
    
    // Obtener datos del nuevo código
    $stmt = $pdo->prepare('
        SELECT code, UNIX_TIMESTAMP(expires_at) as expires_timestamp,
               TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining
        FROM device_security_codes
        WHERE id = LAST_INSERT_ID()
    ');
    $stmt->execute();
    $code = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'code' => $code['code'],
        'expires_at' => $code['expires_timestamp'],
        'seconds_remaining' => (int)$code['seconds_remaining'],
        'new' => true
    ]);
    
} catch (Exception $e) {
    error_log('get_current_code error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener código'
    ]);
}
