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
require_once __DIR__ . '/../../utils/node_client.php';
require_once __DIR__ . '/../../utils/uuid_helper.php';

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = isset($body['username']) ? trim($body['username']) : null;

    // Validar username
    if (!$username || strlen($username) < 3 || strlen($username) > 20) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username must be between 3 and 20 characters'
        ]);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username can only contain letters, numbers and underscores'
        ]);
        exit;
    }

    // Generar UUID v4 válido
    $challengeId = generateUUID();

    // Llamar al backend de Node.js para iniciar el registro
    try {
        $nodeResponse = nodeApiRequest('POST', 'auth/passkey/register/start', [
            'username' => $username,
            'challengeId' => $challengeId,
        ]);
        
        if (!$nodeResponse || !isset($nodeResponse['data'])) {
            throw new Exception('Invalid response from backend: missing data field');
        }
        
        if (!isset($nodeResponse['data']['publicKey'])) {
            throw new Exception('Invalid response from backend: missing publicKey');
        }
    } catch (Exception $nodeError) {
        error_log('passkey_register_start.php - Node backend error: ' . $nodeError->getMessage());
        throw new RuntimeException(
            'Registration service unavailable: ' . $nodeError->getMessage(),
            $nodeError->getCode() ?: 503
        );
    }

    // Guardar el challenge en sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['passkey_register_challenge'] = [
        'id' => $challengeId,
        'username' => $username,
        'created_at' => time(),
    ];

    echo json_encode([
        'success' => true,
        'challengeId' => $challengeId,
        'publicKey' => $nodeResponse['data']['publicKey'],
        'web3AuthClientId' => $nodeResponse['data']['web3AuthClientId'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log('passkey_register_start.php error: ' . $e->getMessage());
    error_log('passkey_register_start.php stack: ' . $e->getTraceAsString());
    
    $httpCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpCode);
    
    $userMessage = 'Unable to start registration. ';
    if (strpos($e->getMessage(), 'service unavailable') !== false) {
        $userMessage .= 'The registration service is temporarily unavailable.';
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        $userMessage .= 'Cannot connect to registration service.';
    } else {
        $userMessage .= $e->getMessage();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $userMessage,
    ]);
}
