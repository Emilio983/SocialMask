<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
require_once __DIR__ . '/../../utils/node_client.php';

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $code = isset($body['link_code']) ? strtoupper(trim($body['link_code'])) : null;
    $qrToken = isset($body['qr_token']) ? trim($body['qr_token']) : null;

    if (empty($code) && empty($qrToken)) {
        throw new RuntimeException('Código o token requerido');
    }

    $nodeResponse = nodeApiRequest('POST', 'devices/link/validate', [
        'linkCode' => $code,
        'qrToken' => $qrToken,
    ]);

    $data = $nodeResponse['data'] ?? $nodeResponse;

    echo json_encode([
        'success' => true,
        'link' => $data,
    ]);
} catch (Throwable $e) {
    error_log('devices/link_validate error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
