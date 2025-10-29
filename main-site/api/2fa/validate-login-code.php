<?php
/**
 * Validate Login Code
 * Validates the 6-digit code entered in Devices page and authorizes the new device
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';

// Check if user is authenticated (must be logged in on this device to authorize another)
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get code from POST
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Code required']);
    exit;
}

$code = trim($input['code']);

// Validate code format (6 digits)
if (!preg_match('/^\d{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid code format']);
    exit;
}

try {
    // Hash the code with user_id to look it up
    $codeHash = hash('sha256', $code . $userId);

    // Find the code in device_link_codes table
    $stmt = $pdo->prepare("
        SELECT link_id, session_token, expires_at, status
        FROM device_link_codes
        WHERE user_id = ? AND code_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $codeHash]);
    $linkCode = $stmt->fetch();

    if (!$linkCode) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Código no encontrado o inválido']);
        exit;
    }

    // Check if already used
    if ($linkCode['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Este código ya fue utilizado']);
        exit;
    }

    // Check if expired
    $expiresAt = new DateTime($linkCode['expires_at']);
    $now = new DateTime();
    if ($now > $expiresAt) {
        // Mark as expired
        $stmt = $pdo->prepare("UPDATE device_link_codes SET status = 'expired' WHERE link_id = ?");
        $stmt->execute([$linkCode['link_id']]);

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Código expirado. Genera uno nuevo.']);
        exit;
    }

    // Code is valid! Mark as used
    $stmt = $pdo->prepare("UPDATE device_link_codes SET status = 'used', used_at = NOW() WHERE link_id = ?");
    $stmt->execute([$linkCode['link_id']]);

    // Log the successful authorization
    error_log("Device authorization successful for user_id: $userId with code: $code");

    // Optionally: Create a session or device entry for the new device
    // This depends on your existing device registration flow

    echo json_encode([
        'success' => true,
        'message' => 'Dispositivo autorizado correctamente',
        'session_token' => $linkCode['session_token']
    ]);

} catch (Exception $e) {
    error_log("Error validating login code: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al validar el código']);
}
