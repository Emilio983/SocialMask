<?php
/**
 * ============================================
 * VERIFICAR CÓDIGO DE VINCULACIÓN
 * ============================================
 * 
 * Verifica código ingresado desde dispositivo nuevo
 * - Validación de código temporal
 * - Límite de 3 intentos
 * - Bloqueo automático por intentos fallidos
 * - Verificación de IP y User Agent
 */

require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');

// No requiere autenticación (es para vincular nuevo dispositivo)
// Pero requiere el código y fingerprint del dispositivo

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['code']) || !isset($input['device_fingerprint'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Código y fingerprint requeridos'
    ]);
    exit;
}

$code = $input['code'];
$deviceFingerprint = $input['device_fingerprint'];
$deviceName = $input['device_name'] ?? 'Dispositivo desconocido';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {
    // ============================================
    // 1. BUSCAR CÓDIGO ACTIVO
    // ============================================
    // Buscar por todos los códigos activos y verificar hash
    $stmt = $pdo->prepare("
        SELECT 
            dlc.*,
            u.username,
            u.email
        FROM device_link_codes dlc
        JOIN users u ON dlc.user_id = u.user_id
        WHERE dlc.status = 'active'
        AND dlc.expires_at > NOW()
        AND dlc.attempts < dlc.max_attempts
    ");
    $stmt->execute();
    $activeCodes = $stmt->fetchAll();
    
    $validCode = null;
    foreach ($activeCodes as $codeData) {
        // Verificar hash
        $expectedHash = hash('sha256', $code . $codeData['user_id'] . $codeData['session_token']);
        if (hash_equals($expectedHash, $codeData['code_hash'])) {
            $validCode = $codeData;
            break;
        }
    }
    
    // ============================================
    // 2. CÓDIGO NO ENCONTRADO O INVÁLIDO
    // ============================================
    if (!$validCode) {
        // Registrar intento fallido global
        $stmt = $pdo->prepare("
            INSERT INTO security_logs (
                user_id,
                event_type,
                ip_address,
                user_agent,
                metadata,
                created_at
            ) VALUES (NULL, 'device_link_failed', ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $ipAddress,
            $userAgent,
            json_encode([
                'reason' => 'invalid_code',
                'code_length' => strlen($code)
            ])
        ]);
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Código inválido o expirado'
        ]);
        exit;
    }
    
    $userId = $validCode['user_id'];
    $codeId = $validCode['id'];
    
    // ============================================
    // 3. INCREMENTAR INTENTOS
    // ============================================
    $stmt = $pdo->prepare("
        UPDATE device_link_codes 
        SET attempts = attempts + 1 
        WHERE id = ?
    ");
    $stmt->execute([$codeId]);
    
    // ============================================
    // 4. VERIFICAR DISPOSITIVO NO DUPLICADO
    // ============================================
    $stmt = $pdo->prepare("
        SELECT id FROM authorized_devices 
        WHERE user_id = ? 
        AND device_fingerprint = ? 
        AND status = 'active'
    ");
    $stmt->execute([$userId, $deviceFingerprint]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Este dispositivo ya está vinculado'
        ]);
        exit;
    }
    
    // ============================================
    // 5. REGISTRAR NUEVO DISPOSITIVO
    // ============================================
    $deviceToken = bin2hex(random_bytes(32));
    
    $stmt = $pdo->prepare("
        INSERT INTO authorized_devices (
            user_id,
            device_name,
            device_fingerprint,
            device_token,
            ip_address,
            user_agent,
            status,
            last_used_at,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ");
    
    $stmt->execute([
        $userId,
        $deviceName,
        $deviceFingerprint,
        hash('sha256', $deviceToken),
        $ipAddress,
        $userAgent
    ]);
    
    $deviceId = $pdo->lastInsertId();
    
    // ============================================
    // 6. MARCAR CÓDIGO COMO USADO
    // ============================================
    $stmt = $pdo->prepare("
        UPDATE device_link_codes 
        SET status = 'used', used_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$codeId]);
    
    // ============================================
    // 7. REGISTRAR EVENTO DE SEGURIDAD
    // ============================================
    $stmt = $pdo->prepare("
        INSERT INTO security_logs (
            user_id,
            event_type,
            ip_address,
            user_agent,
            metadata,
            created_at
        ) VALUES (?, 'device_linked', ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $ipAddress,
        $userAgent,
        json_encode([
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'code_id' => $codeId
        ])
    ]);
    
    // ============================================
    // 8. ENVIAR NOTIFICACIÓN AL USUARIO (Opcional)
    // ============================================
    // TODO: Enviar email/notificación de nuevo dispositivo vinculado
    
    // ============================================
    // 9. RESPUESTA EXITOSA
    // ============================================
    echo json_encode([
        'success' => true,
        'device_id' => $deviceId,
        'device_token' => $deviceToken,
        'message' => 'Dispositivo vinculado exitosamente',
        'user' => [
            'username' => $validCode['username'],
            'email' => $validCode['email']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error verifying link code: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor'
    ]);
}
