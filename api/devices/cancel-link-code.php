<?php
/**
 * ============================================
 * CANCELAR CÓDIGO DE VINCULACIÓN
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

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['session_token'] ?? null;

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Token requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE device_link_codes 
        SET status = 'expired' 
        WHERE session_token = ? 
        AND user_id = ? 
        AND status = 'active'
    ");
    
    $stmt->execute([$token, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Error canceling code: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
