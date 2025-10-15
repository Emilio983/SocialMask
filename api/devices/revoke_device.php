<?php
/**
 * API: Revocar dispositivo
 * POST /api/devices/revoke_device.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';

requireAuth();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $deviceId = (int)($input['device_id'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    if ($deviceId <= 0) {
        throw new Exception('Device ID inválido');
    }
    
    // Verificar que el dispositivo pertenece al usuario
    $stmt = $pdo->prepare('
        UPDATE authorized_devices
        SET status = "revoked"
        WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([$deviceId, $userId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Dispositivo no encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Dispositivo revocado'
    ]);
    
} catch (Exception $e) {
    error_log('revoke_device error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
