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

function generateUniqueUsername(PDO $pdo): string
{
    do {
        $candidate = 'anon_' . substr(bin2hex(random_bytes(6)), 0, 12);
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$candidate]);
        $exists = $stmt->fetch();
    } while ($exists);

    return $candidate;
}

try {
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['passkey_challenge']['id'])) {
        throw new RuntimeException('Missing passkey challenge in session');
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || empty($body['challengeId']) || empty($body['credential'])) {
        throw new RuntimeException('Invalid payload');
    }

    $challengeMeta = $_SESSION['passkey_challenge'];

    if ($body['challengeId'] !== $challengeMeta['id']) {
        throw new RuntimeException('Challenge mismatch');
    }

    if (isset($challengeMeta['created_at']) && (time() - (int) $challengeMeta['created_at']) > 180) {
        unset($_SESSION['passkey_challenge']);
        throw new RuntimeException('El challenge expiró, intenta nuevamente');
    }

    $linkCode = isset($body['linkCode']) ? strtoupper(trim($body['linkCode'])) : null;
    $qrToken = isset($body['qrToken']) ? trim($body['qrToken']) : null;

    $nodePayload = [
        'challengeId' => $body['challengeId'],
        'credential' => $body['credential'],
        'userAgent' => $body['platform'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
    ];

    if (!empty($body['web3Auth']) && is_array($body['web3Auth'])) {
        $nodePayload['web3Auth'] = $body['web3Auth'];
    }

    $nodeResponse = nodeApiRequest('POST', 'auth/passkey/finish', $nodePayload);

    $data = $nodeResponse['data'] ?? $nodeResponse;

    if (empty($data['ownerAddress']) || empty($data['devicePublicKey'])) {
        error_log('passkey_finish.php - Node response missing data: ' . json_encode($data));
        throw new RuntimeException('Node response missing owner data');
    }

    $ownerAddress = strtolower($data['ownerAddress']);
    $devicePublicKey = $data['devicePublicKey'];
    $credentialId = $data['credentialId'] ?? null;
    $credentialBinary = null;
    if ($credentialId) {
        $normalized = strtr($credentialId, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($normalized, true);
        if ($decoded !== false) {
            $credentialBinary = $decoded;
        }
    }

    if (!empty($linkCode) || !empty($qrToken)) {
        $consumeResponse = nodeApiRequest('POST', 'devices/link/consume', [
            'linkCode' => $linkCode,
            'qrToken' => $qrToken,
            'devicePublicKey' => $devicePublicKey,
            'credentialId' => $credentialId,
            'deviceLabel' => $body['deviceLabel'] ?? null,
            'platform' => $body['platform'] ?? null,
            'ownerAddress' => $ownerAddress,
        ]);

        $payload = $consumeResponse['data'] ?? $consumeResponse;
        $userData = $payload['user'] ?? null;

        if (!$userData || empty($userData['user_id'])) {
            throw new RuntimeException('No se pudo vincular el dispositivo');
        }

        $_SESSION['user_id'] = (int) $userData['user_id'];
        $_SESSION['wallet_address'] = strtolower($userData['wallet_address'] ?? $ownerAddress);
        $_SESSION['username'] = $userData['username'];
        $_SESSION['login_time'] = time();

        unset($_SESSION['passkey_challenge']);

        echo json_encode([
            'success' => true,
            'user' => [
                'user_id' => (int) $userData['user_id'],
                'username' => $userData['username'],
                'wallet_address' => strtolower($userData['wallet_address'] ?? $ownerAddress),
                'smart_account_address' => $userData['smart_account_address'] ?? null,
            ],
        ]);
        return;
    }

    $pdo->beginTransaction();

    // Buscar usuario por wallet_address
    $stmt = $pdo->prepare('SELECT * FROM users WHERE wallet_address = ? LIMIT 1');
    $stmt->execute([$ownerAddress]);
    $user = $stmt->fetch();

    // Si no existe el usuario, intentar buscar por owner_address (campo alternativo)
    if (!$user) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE owner_address = ? LIMIT 1');
        $stmt->execute([$ownerAddress]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        // ❌ NO CREAR USUARIO AUTOMÁTICAMENTE EN LOGIN
        // El usuario debe registrarse primero
        $pdo->rollBack();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuario no encontrado. Por favor regístrate primero.',
            'action' => 'register'
        ]);
        exit;
    }

    $userId = (int) $user['user_id'];

    // Obtener smart account existente
    $smartAccountAddress = $user['smart_account_address'];
    
    // Si NO tiene smart account, programar creación en BACKGROUND (no bloquea login)
    if (empty($smartAccountAddress)) {
        // Guardar datos necesarios en sesión para crear después
        $_SESSION['pending_smart_account'] = [
            'ownerAddress' => $ownerAddress,
            'devicePublicKey' => $devicePublicKey,
            'userId' => $userId,
            'timestamp' => time()
        ];
    }

    // Nota: paymaster_policy_id no está disponible en esta tabla
    $paymasterPolicyId = null;

    $deviceSql = 'INSERT INTO user_devices (user_id, device_label, device_public_key, credential_id, platform, is_primary, added_via, last_used_at, revoked_at, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NULL, NOW())
                  ON DUPLICATE KEY UPDATE device_public_key = VALUES(device_public_key), revoked_at = NULL, last_used_at = NOW(), platform = VALUES(platform)';

    // Truncar platform a 50 caracteres para evitar errores
    $platform = $body['platform'] ?? null;
    if ($platform && strlen($platform) > 50) {
        error_log('passkey_finish.php - Truncating platform from ' . strlen($platform) . ' to 50 chars');
        $platform = substr($platform, 0, 50);
    }

    try {
        $deviceStmt = $pdo->prepare($deviceSql);
        $deviceStmt->execute([
            $userId,
            $body['deviceLabel'] ?? null,
            $devicePublicKey,
            $credentialBinary,
            $platform,
            isset($user['primary_device_id']) ? 0 : 1,
            $body['linkMethod'] ?? 'onboarding',
        ]);
    } catch (PDOException $deviceError) {
        error_log('passkey_finish.php - Device insert error: ' . $deviceError->getMessage());
        error_log('passkey_finish.php - Device data: userId=' . $userId . ', devicePublicKey=' . substr($devicePublicKey, 0, 20) . '..., platform=' . $platform);
        throw $deviceError;
    }

    $deviceId = null;
    if ($credentialId) {
        $deviceQuery = $pdo->prepare('SELECT id FROM user_devices WHERE credential_id = ? LIMIT 1');
        $deviceQuery->execute([$credentialBinary]);
        $deviceRow = $deviceQuery->fetch();
        $deviceId = $deviceRow ? (int) $deviceRow['id'] : null;
    }

    if (!$deviceId) {
        $deviceQuery = $pdo->prepare('SELECT id FROM user_devices WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $deviceQuery->execute([$userId]);
        $deviceRow = $deviceQuery->fetch();
        $deviceId = $deviceRow ? (int) $deviceRow['id'] : null;
    }

    if (empty($user['primary_device_id']) && $deviceId) {
        $pdo->prepare('UPDATE users SET primary_device_id = ? WHERE user_id = ?')->execute([$deviceId, $userId]);
        $user['primary_device_id'] = $deviceId;
    }

    $pdo->prepare('UPDATE users SET last_login = NOW(), wallet_type = ?, updated_at = NOW() WHERE user_id = ?')
        ->execute(['passkey', $userId]);

    $pdo->commit();

    $_SESSION['user_id'] = $userId;
    $_SESSION['wallet_address'] = $ownerAddress;
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();

    unset($_SESSION['passkey_challenge']);

    echo json_encode([
        'success' => true,
        'user' => [
            'user_id' => $userId,
            'username' => $user['username'],
            'wallet_address' => $ownerAddress,
            'smart_account_address' => $smartAccountAddress,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMessage = $e->getMessage();
    $errorCode = $e->getCode();
    
    error_log('passkey_finish.php error: ' . $errorMessage);
    error_log('passkey_finish.php trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    
    // Mensaje más específico según el error
    $userMessage = 'Unable to finish passkey login';
    if (strpos($errorMessage, 'Column not found') !== false || strpos($errorMessage, 'Unknown column') !== false) {
        $userMessage .= ': Database schema error. Please contact support.';
        error_log('passkey_finish.php - DATABASE SCHEMA ERROR: ' . $errorMessage);
    } elseif (strpos($errorMessage, 'Missing passkey challenge') !== false) {
        $userMessage = 'Session expired. Please try logging in again.';
    } elseif (strpos($errorMessage, 'Challenge mismatch') !== false) {
        $userMessage = 'Invalid challenge. Please try logging in again.';
    } elseif (strpos($errorMessage, 'Usuario no encontrado') !== false || strpos($errorMessage, 'User not found') !== false) {
        $userMessage = 'Usuario no encontrado. Por favor regístrate primero.';
    } elseif (strpos($errorMessage, 'Node response missing') !== false) {
        $userMessage = 'Authentication service error. Please try again.';
    } else {
        $userMessage .= '. Please try again.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $userMessage,
        'debug' => (defined('DEBUG') && DEBUG) ? $errorMessage : null,
    ]);
}
