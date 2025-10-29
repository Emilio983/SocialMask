<?php
/**
 * Generate Login Code - Sin autenticación
 * El usuario en el login genera un código para autorizarse desde otro dispositivo
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/connection.php';

// Obtener username del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username required']);
    exit;
}

$username = trim($input['username']);

try {
    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $userId = $user['user_id'];

    // Generar código de 6 dígitos
    $code = sprintf('%06d', random_int(0, 999999));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Guardar en la tabla device_link_codes (para login temporal)
    $sessionToken = bin2hex(random_bytes(32));
    $codeHash = hash('sha256', $code . $userId);

    $stmt = $pdo->prepare("
        INSERT INTO device_link_codes (user_id, code_hash, session_token, expires_at, status)
        VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$userId, $codeHash, $sessionToken, $expiresAt]);

    echo json_encode([
        'success' => true,
        'code' => $code,
        'session_token' => $sessionToken,
        'expires_at' => $expiresAt,
        'expires_in_seconds' => 600
    ]);

} catch (Exception $e) {
    error_log("Error generating login code: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to generate code']);
}
