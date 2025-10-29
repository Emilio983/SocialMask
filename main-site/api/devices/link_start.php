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
    $userId = (int) $_SESSION['user_id'];

    $nodeResponse = nodeApiRequest('POST', 'devices/link/start', [
        'userId' => $userId,
    ]);

    $data = $nodeResponse['data'] ?? $nodeResponse;

    echo json_encode([
        'success' => true,
        'link_code' => $data['linkCode'] ?? null,
        'qr_token' => $data['qrToken'] ?? null,
        'expires_at' => $data['expiresAt'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log('devices/link_start error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo generar el código de vinculación',
    ]);
}
