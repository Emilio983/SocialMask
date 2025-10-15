<?php
/**
 * API: Listar dispositivos autorizados
 * GET /api/devices/list_devices.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';

requireAuth();

try {
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare('
        SELECT id, device_name, ip_address, status,
               DATE_FORMAT(authorized_at, "%Y-%m-%d %H:%i") as authorized_date,
               DATE_FORMAT(last_used, "%Y-%m-%d %H:%i") as last_used_date
        FROM authorized_devices
        WHERE user_id = ?
        ORDER BY authorized_at DESC
    ');
    $stmt->execute([$userId]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'devices' => $devices
    ]);
    
} catch (Exception $e) {
    error_log('list_devices error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al listar dispositivos'
    ]);
}
