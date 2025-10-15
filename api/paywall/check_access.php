<?php
/**
 * API: Verificar Acceso a Contenido
 * Endpoint: GET /api/paywall/check_access.php?content_id={id}
 * 
 * Verifica si un usuario tiene acceso a contenido de pago
 * Retorna true/false
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/response.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Autenticación requerida
$user = authenticate();
if (!$user) {
    sendError('Unauthorized', 401);
}

// Content ID requerido
if (!isset($_GET['content_id']) || empty($_GET['content_id'])) {
    sendError('Content ID is required', 400);
}

$content_id = (int)$_GET['content_id'];

if ($content_id <= 0) {
    sendError('Invalid content ID', 400);
}

try {
    $db = getConnection();
    
    // Verificar que el contenido exista
    $stmt = $db->prepare("
        SELECT id, user_id, contract_content_id, title
        FROM paywall_content
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$content_id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$content) {
        sendError('Content not found', 404);
    }
    
    $has_access = false;
    $access_reason = '';
    
    // 1. Verificar si es el creador
    if ($content['user_id'] == $user['id']) {
        $has_access = true;
        $access_reason = 'creator';
    }
    
    // 2. Verificar compra confirmada en DB
    if (!$has_access) {
        $stmt = $db->prepare("
            SELECT 
                id, 
                tx_hash, 
                purchased_at,
                confirmed_at
            FROM paywall_purchases
            WHERE content_id = ? 
            AND user_id = ? 
            AND status = 'confirmed'
            LIMIT 1
        ");
        $stmt->execute([$content_id, $user['id']]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($purchase) {
            $has_access = true;
            $access_reason = 'purchase';
            $purchase_info = $purchase;
        }
    }
    
    // 3. Verificar en tabla de accesos (caché de blockchain)
    if (!$has_access) {
        $stmt = $db->prepare("
            SELECT 
                granted_at,
                last_verified
            FROM paywall_access
            WHERE content_id = ? 
            AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$content_id, $user['id']]);
        $access = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($access) {
            $has_access = true;
            $access_reason = 'blockchain_verified';
            $access_info = $access;
        }
    }
    
    // Respuesta
    $response = [
        'has_access' => $has_access,
        'content_id' => $content_id,
        'contract_content_id' => $content['contract_content_id'],
        'user_id' => $user['id'],
        'access_reason' => $access_reason
    ];
    
    // Agregar información adicional según el tipo de acceso
    if ($has_access) {
        if (isset($purchase_info)) {
            $response['purchase'] = $purchase_info;
        }
        if (isset($access_info)) {
            $response['access'] = $access_info;
        }
    } else {
        $response['message'] = 'Access denied. Purchase required.';
    }
    
    sendSuccess($response);
    
} catch (PDOException $e) {
    error_log("Database error in check_access: " . $e->getMessage());
    sendError('Database error', 500);
} catch (Exception $e) {
    error_log("Error in check_access: " . $e->getMessage());
    sendError('Server error', 500);
}
