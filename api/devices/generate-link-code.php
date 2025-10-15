<?php
/**
 * ============================================
 * GENERAR CÓDIGO DE VINCULACIÓN DE DISPOSITIVO
 * ============================================
 * 
 * Sistema de vinculación seguro con código temporal (TOTP)
 * - Código cambia cada 5 minutos
 * - Solo válido para el usuario actual
 * - Protección contra fuerza bruta
 * - Rate limiting
 * - Registro de intentos
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/utils.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autenticado'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // ============================================
    // 1. RATE LIMITING - Prevenir abuso
    // ============================================
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM device_link_codes 
        WHERE user_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$userId]);
    $rateLimit = $stmt->fetch();
    
    if ($rateLimit['attempts'] >= 10) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Demasiados intentos. Espera 1 hora.'
        ]);
        exit;
    }
    
    // ============================================
    // 2. GENERAR CÓDIGO ÚNICO DE 8 DÍGITOS
    // ============================================
    // Usar cryptographically secure random
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= random_int(0, 9);
    }
    
    // ============================================
    // 3. GENERAR TOKEN DE SESIÓN ÚNICO
    // ============================================
    $sessionToken = bin2hex(random_bytes(32));
    
    // ============================================
    // 4. CALCULAR TIEMPO DE EXPIRACIÓN (5 minutos)
    // ============================================
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // ============================================
    // 5. HASH DEL CÓDIGO PARA SEGURIDAD
    // ============================================
    // No guardamos el código en texto plano
    $codeHash = hash('sha256', $code . $userId . $sessionToken);
    
    // ============================================
    // 6. INVALIDAR CÓDIGOS ANTERIORES
    // ============================================
    $stmt = $pdo->prepare("
        UPDATE device_link_codes 
        SET status = 'expired' 
        WHERE user_id = ? 
        AND status = 'active'
    ");
    $stmt->execute([$userId]);
    
    // ============================================
    // 7. GUARDAR NUEVO CÓDIGO
    // ============================================
    $stmt = $pdo->prepare("
        INSERT INTO device_link_codes (
            user_id,
            code_hash,
            session_token,
            expires_at,
            status,
            attempts,
            max_attempts,
            created_at
        ) VALUES (?, ?, ?, ?, 'active', 0, 3, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $codeHash,
        $sessionToken,
        $expiresAt
    ]);
    
    $linkCodeId = $pdo->lastInsertId();
    
    // ============================================
    // 8. REGISTRAR EVENTO DE SEGURIDAD
    // ============================================
    $stmt = $pdo->prepare("
        INSERT INTO security_logs (
            user_id,
            event_type,
            ip_address,
            user_agent,
            metadata,
            created_at
        ) VALUES (?, 'device_code_generated', ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        json_encode([
            'code_id' => $linkCodeId,
            'expires_at' => $expiresAt
        ])
    ]);
    
    // ============================================
    // 9. RESPUESTA EXITOSA
    // ============================================
    echo json_encode([
        'success' => true,
        'code' => $code, // Solo se envía una vez
        'session_token' => $sessionToken,
        'expires_at' => $expiresAt,
        'expires_in_seconds' => 300, // 5 minutos
        'code_id' => $linkCodeId
    ]);
    
} catch (PDOException $e) {
    error_log("Error generating link code: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor'
    ]);
}
