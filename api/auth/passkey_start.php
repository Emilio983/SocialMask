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
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $redirectUri = isset($body['redirect_uri']) ? filter_var($body['redirect_uri'], FILTER_SANITIZE_URL) : null;

    // Usar challengeId del frontend si se proporciona, sino generar uno nuevo
    $challengeId = isset($body['challengeId']) && !empty($body['challengeId'])
        ? $body['challengeId']
        : generateUUID();

    // Intentar llamar al backend Node.js
    try {
        $payload = [
            'challengeId' => $challengeId,
        ];
        
        // Solo incluir redirectUri si está presente
        if ($redirectUri !== null) {
            $payload['redirectUri'] = $redirectUri;
        }
        
        $nodeResponse = nodeApiRequest('POST', 'auth/passkey/start', $payload);
        
        // Verificar que la respuesta es válida
        if (!is_array($nodeResponse) || !isset($nodeResponse['data'])) {
            throw new RuntimeException('Invalid response from Node backend: missing data field');
        }
        
    } catch (Exception $nodeError) {
        // Si el backend Node falla, log detallado del error
        error_log('passkey_start.php - Node backend error: ' . $nodeError->getMessage());
        error_log('passkey_start.php - Node backend code: ' . $nodeError->getCode());
        
        // Re-lanzar el error con más contexto
        throw new RuntimeException(
            'Backend service unavailable. Please contact support. Error: ' . $nodeError->getMessage(),
            $nodeError->getCode() ?: 503,
            $nodeError
        );
    }

    $challenge = $nodeResponse['data']['challenge'] ?? null;
    
    if (!$challenge) {
        throw new RuntimeException('No challenge received from backend');
    }

    $_SESSION['passkey_challenge'] = [
        'id' => $challengeId,
        'challenge' => $challenge,
        'created_at' => time(),
    ];

    echo json_encode([
        'success' => true,
        'challengeId' => $challengeId,
        'data' => $nodeResponse['data'],
    ]);
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
    $errorCode = $e->getCode();
    
    // Log detallado del error
    error_log('passkey_start.php error: ' . $errorMessage . ' (code: ' . $errorCode . ')');
    error_log('passkey_start.php stack: ' . $e->getTraceAsString());
    
    // Determinar código de respuesta HTTP apropiado
    $httpCode = ($errorCode >= 400 && $errorCode < 600) ? $errorCode : 500;
    http_response_code($httpCode);
    
    // Determinar si mostrar detalles del error
    $isDebug = defined('DEBUG') && DEBUG === true;
    
    // Mensaje amigable para el usuario
    $userMessage = 'Unable to initiate passkey flow. ';
    if (strpos($errorMessage, 'Backend service unavailable') !== false) {
        $userMessage .= 'The authentication service is currently unavailable.';
    } elseif (strpos($errorMessage, 'Connection refused') !== false || strpos($errorMessage, 'Could not connect') !== false) {
        $userMessage .= 'Cannot connect to authentication service.';
    } else {
        $userMessage .= 'Please try again later.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $userMessage,
        'error' => $isDebug ? $errorMessage : null,
        'code' => $isDebug ? $errorCode : null,
    ]);
}
