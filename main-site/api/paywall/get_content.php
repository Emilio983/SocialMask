<?php
/**
 * API: Obtener Contenido de Pago
 * Endpoint: GET /api/paywall/get_content.php?id={content_id}
 * 
 * Obtiene información de contenido de pago
 * Si el usuario tiene acceso, retorna el contenido completo
 * Si no tiene acceso, retorna solo el preview
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

// Content ID es requerido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    sendError('Content ID is required', 400);
}

$content_id = (int)$_GET['id'];

if ($content_id <= 0) {
    sendError('Invalid content ID', 400);
}

// Usuario autenticado (opcional)
$user = authenticate(false); // false = no requerido

try {
    $db = getConnection();
    
    // Obtener contenido
    $stmt = $db->prepare("
        SELECT * FROM v_paywall_content_full
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$content_id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$content) {
        sendError('Content not found', 404);
    }
    
    // Incrementar views
    $stmt = $db->prepare("
        INSERT INTO paywall_stats (content_id, views, unique_viewers)
        VALUES (?, 1, 1)
        ON DUPLICATE KEY UPDATE
            views = views + 1
    ");
    $stmt->execute([$content_id]);
    
    // Verificar acceso del usuario
    $has_access = false;
    $is_creator = false;
    
    if ($user) {
        // Es el creador?
        $is_creator = ($content['user_id'] == $user['id']);
        
        if ($is_creator) {
            $has_access = true;
        } else {
            // Verificar si tiene compra confirmada
            $stmt = $db->prepare("
                SELECT 1 FROM paywall_purchases
                WHERE content_id = ? 
                AND user_id = ? 
                AND status = 'confirmed'
                LIMIT 1
            ");
            $stmt->execute([$content_id, $user['id']]);
            $has_access = (bool)$stmt->fetch();
        }
        
        // Si no tiene acceso, verificar en blockchain como fallback
        if (!$has_access && isset($user['wallet_address'])) {
            $stmt = $db->prepare("
                SELECT 1 FROM paywall_access
                WHERE content_id = ? 
                AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$content_id, $user['id']]);
            $has_access = (bool)$stmt->fetch();
        }
    }
    
    // Preparar respuesta según acceso
    $response = [
        'id' => $content['id'],
        'contract_content_id' => $content['contract_content_id'],
        'title' => $content['title'],
        'description' => $content['description'],
        'price' => $content['price'],
        'content_type' => $content['content_type'],
        'creator_username' => $content['creator_username'],
        'creator_wallet' => $content['creator_wallet'],
        'total_sales' => $content['total_sales'],
        'views' => $content['views'],
        'unique_viewers' => $content['unique_viewers'],
        'purchases' => $content['purchases'],
        'conversion_rate' => $content['conversion_rate'],
        'created_at' => $content['created_at'],
        'has_access' => $has_access,
        'is_creator' => $is_creator
    ];
    
    if ($has_access) {
        // Usuario tiene acceso - retornar contenido completo
        $response['content_url'] = $content['content_url'];
        $response['full_content'] = true;
        
        // Log de acceso
        if ($user && !$is_creator) {
            $stmt = $db->prepare("
                INSERT INTO activity_log (user_id, action, details)
                VALUES (?, 'paywall_access', ?)
            ");
            $stmt->execute([
                $user['id'],
                json_encode(['content_id' => $content_id])
            ]);
        }
    } else {
        // Usuario no tiene acceso - retornar solo preview
        $response['preview_url'] = $content['preview_url'];
        $response['preview_text'] = $content['preview_text'];
        $response['full_content'] = false;
        $response['message'] = 'Purchase required to access full content';
    }
    
    sendSuccess($response);
    
} catch (PDOException $e) {
    error_log("Database error in get_content: " . $e->getMessage());
    sendError('Database error', 500);
} catch (Exception $e) {
    error_log("Error in get_content: " . $e->getMessage());
    sendError('Server error', 500);
}
