<?php
// ============================================
// GET NONCE - Generar nonce para autenticación
// ============================================

// IMPORTANTE: Headers PRIMERO, antes de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar archivo antes de incluir
$conn_file = __DIR__ . '/../config/connection.php';
if (!file_exists($conn_file)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration file not found']);
    exit;
}
require_once $conn_file;

// Rate limiting - 10 requests per minute per IP
require_once __DIR__ . '/rate_limiter.php';
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
checkRateLimit('nonce_' . $client_ip, 10, 60);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['wallet_address'])) {
        throw new Exception('Wallet address is required');
    }

    $wallet_address = strtolower(trim($input['wallet_address']));

    // Validate wallet address format
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
        throw new Exception('Invalid wallet address format');
    }

    // Generate nonce
    $nonce = bin2hex(random_bytes(16));
    $expires_at = date('Y-m-d H:i:s', time() + NONCE_EXPIRY);

    // Store nonce in database
    $stmt = $pdo->prepare("
        INSERT INTO auth_nonces (wallet_address, nonce, expires_at)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
        nonce = VALUES(nonce),
        expires_at = VALUES(expires_at)
    ");

    $stmt->execute([$wallet_address, $nonce, $expires_at]);

    echo json_encode([
        'success' => true,
        'nonce' => $nonce,
        'expires_at' => $expires_at,
        'message' => 'Nonce generated successfully'
    ]);

} catch (Exception $e) {
    error_log("ERROR - get_nonce.php: " . $e->getMessage());
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Unknown error occurred'
    ]);
} catch (Throwable $e) {
    error_log("CRITICAL - get_nonce.php: " . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>