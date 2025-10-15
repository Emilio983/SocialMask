<?php
/**
 * ============================================
 * VERIFICAR ESTADO DE CÓDIGO
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/utils.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$token = $_GET['token'] ?? null;

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Token requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT status, used_at 
        FROM device_link_codes 
        WHERE session_token = ? 
        AND user_id = ?
    ");
    
    $stmt->execute([$token, $_SESSION['user_id']]);
    $code = $stmt->fetch();
    
    if (!$code) {
        http_response_code(404);
        echo json_encode(['error' => 'Código no encontrado']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'status' => $code['status'],
        'used_at' => $code['used_at']
    ]);
    
} catch (PDOException $e) {
    error_log("Error checking code status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
