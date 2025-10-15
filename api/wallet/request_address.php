<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $pdo = getConnection();

    // Obtener smart account address desde la tabla smart_accounts
    $stmt = $pdo->prepare('
        SELECT sa.smart_account_address, sa.is_deployed
        FROM smart_accounts sa
        WHERE sa.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['smart_account_address'])) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'message' => 'Smart Account no encontrada. Por favor intenta registrarte de nuevo.'
        ]);
        exit;
    }

    $smartAccount = $user['smart_account_address'];

    // La dirección de depósito es la misma que la Smart Account
    // En Polygon, puedes recibir directamente en tu Smart Account
    echo json_encode([
        'success' => true,
        'data' => [
            'deposit_address' => $smartAccount,
            'smart_account_address' => $smartAccount,
            'expires_at' => null, // Las direcciones permanentes no expiran
            'network' => 'Polygon',
            'is_deployed' => (bool) ($user['is_deployed'] ?? false)
        ],
    ]);
    
} catch (Throwable $e) {
    error_log('request_address.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener dirección. Por favor intenta de nuevo.',
    ]);
}
