<?php
/**
 * API: Obtener Smart Account del Usuario
 * GET /api/wallet/get_smart_account.php
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';

requireAuth();

try {
    $stmt = $pdo->prepare('
        SELECT smart_account_address, is_deployed, owner_address
        FROM smart_accounts
        WHERE user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || empty($account['smart_account_address'])) {
        throw new RuntimeException('Smart account no encontrada');
    }

    echo json_encode([
        'success' => true,
        'smart_account_address' => $account['smart_account_address'],
        'is_deployed' => (bool)$account['is_deployed'],
        'owner_address' => $account['owner_address']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
