<?php
/**
 * API: Obtener Compras del Usuario
 * Endpoint: GET /api/paywall/my_purchases.php
 * 
 * Lista todas las compras realizadas por el usuario
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

// Parámetros
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    $db = getConnection();
    
    // Construir query
    $where = ["pp.user_id = ?"];
    $params = [$user['id']];
    
    if ($status) {
        $where[] = "pp.status = ?";
        $params[] = $status;
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Contar total
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM paywall_purchases pp
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener compras
    $stmt = $db->prepare("
        SELECT 
            pp.*,
            pc.title as content_title,
            pc.content_type,
            pc.contract_content_id,
            u.username as creator_username,
            u.wallet_address as creator_wallet
        FROM paywall_purchases pp
        JOIN paywall_content pc ON pp.content_id = pc.id
        JOIN users u ON pc.user_id = u.id
        WHERE $where_clause
        ORDER BY pp.purchased_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas del usuario
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_purchases,
            COUNT(DISTINCT content_id) as unique_content,
            SUM(price) as total_spent,
            COUNT(DISTINCT DATE(purchased_at)) as days_active
        FROM paywall_purchases
        WHERE user_id = ? AND status = 'confirmed'
    ");
    $stmt->execute([$user['id']]);
    $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular páginas
    $total_pages = ceil($total / $limit);
    $current_page = floor($offset / $limit) + 1;
    
    sendSuccess([
        'purchases' => $purchases,
        'stats' => [
            'total_purchases' => (int)$user_stats['total_purchases'],
            'unique_content' => (int)$user_stats['unique_content'],
            'total_spent' => $user_stats['total_spent'],
            'days_active' => (int)$user_stats['days_active']
        ],
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'has_more' => $offset + $limit < $total
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in my_purchases: " . $e->getMessage());
    sendError('Database error', 500);
} catch (Exception $e) {
    error_log("Error in my_purchases: " . $e->getMessage());
    sendError('Server error', 500);
}
