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

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = isset($body['username']) ? trim($body['username']) : null;
    $credential = $body['credential'] ?? null;

    // Validar datos requeridos
    if (!$username) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username is required'
        ]);
        exit;
    }

    if (!$credential) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Credential is required'
        ]);
        exit;
    }

    // Recuperar challenge de sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $challengeData = $_SESSION['passkey_register_challenge'] ?? null;
    if (!$challengeData || !isset($challengeData['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No active registration challenge found'
        ]);
        exit;
    }

    // Verificar que el username coincida
    if ($challengeData['username'] !== $username) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username mismatch'
        ]);
        exit;
    }

    // Verificar expiración (5 minutos)
    if (time() - $challengeData['created_at'] > 300) {
        unset($_SESSION['passkey_register_challenge']);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Registration challenge expired'
        ]);
        exit;
    }

    $challengeId = $challengeData['id'];

    // Obtener user agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // TODO: Integrar Web3Auth para obtener idToken
    // Por ahora enviamos null y el backend usará derivación determinística
    $web3Auth = null;

    // Llamar al backend de Node.js para finalizar el registro
    $nodeResponse = nodeApiRequest('POST', 'auth/passkey/register/finish', [
        'username' => $username,
        'challengeId' => $challengeId,
        'credential' => $credential,
        'userAgent' => $userAgent,
        'web3Auth' => $web3Auth,
    ]);

    if (!$nodeResponse || !isset($nodeResponse['data'])) {
        throw new Exception('Invalid response from backend');
    }

    $userData = $nodeResponse['data'];

    // Limpiar el challenge de sesión
    unset($_SESSION['passkey_register_challenge']);

    // Crear sesión de usuario autenticado
    $_SESSION['user_id'] = $userData['userId'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['wallet_address'] = $userData['smart_account_address'];
    $_SESSION['owner_address'] = $userData['owner_address'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $userData['userId'],
            'username' => $userData['username'],
            'smart_account_address' => $userData['smart_account_address'],
            'owner_address' => $userData['owner_address'],
        ],
        'message' => 'Registration successful'
    ]);
} catch (Throwable $e) {
    error_log('passkey_register_finish.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
