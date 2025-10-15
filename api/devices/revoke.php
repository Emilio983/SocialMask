<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../../utils/node_client.php';

requireAuth();

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $deviceId = isset($body['device_id']) ? (int) $body['device_id'] : 0;

    if ($deviceId <= 0) {
        throw new RuntimeException('Device_id requerido');
    }

    $userId = (int) $_SESSION['user_id'];

    $nodeResponse = nodeApiRequest('POST', 'devices/revoke', [
        'userId' => $userId,
        'deviceId' => $deviceId,
    ]);

    $data = $nodeResponse['data'] ?? $nodeResponse;

    echo json_encode([
        'success' => true,
        'device_id' => $data['device_id'] ?? $deviceId,
    ]);
} catch (Throwable $e) {
    error_log('devices/revoke error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
