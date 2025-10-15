<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../../utils/node_client.php';

requireAuth();

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Obtener smart account address desde la tabla smart_accounts
    $stmt = $pdo->prepare('
        SELECT sa.smart_account_address
        FROM smart_accounts sa
        WHERE sa.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || empty($row['smart_account_address'])) {
        throw new RuntimeException('Smart account aún no provisionada.');
    }

    $payload = array_merge($body, [
        'smartAccountAddress' => $row['smart_account_address'],
        'userId' => (int) $_SESSION['user_id'],
    ]);

    $nodeResp = nodeApiRequest('POST', 'wallet/autoswap-execute', $payload);
    $data = $nodeResp['data'] ?? $nodeResp;

    echo json_encode([
        'success' => true,
        'result' => $data,
    ]);
} catch (Throwable $e) {
    error_log('wallet/autoswap_execute error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'No se pudo ejecutar el auto-swap',
    ]);
}
