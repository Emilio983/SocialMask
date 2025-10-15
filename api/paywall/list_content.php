<?php
/**
 * API: Listar Contenido de Pago
 * Endpoint: GET /api/paywall/list_content.php
 * 
 * Lista contenido de pago disponible con filtros
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Parámetros de filtrado
$creator_id = isset($_GET['creator_id']) ? (int)$_GET['creator_id'] : null;
$content_type = isset($_GET['content_type']) ? trim($_GET['content_type']) : null;
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'created_at';
$order = isset($_GET['order']) ? strtoupper(trim($_GET['order'])) : 'DESC';
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Validar sort_by
$valid_sorts = ['created_at', 'price', 'total_sales', 'total_revenue', 'views'];
if (!in_array($sort_by, $valid_sorts)) {
    $sort_by = 'created_at';
}

// Validar order
if ($order !== 'ASC' && $order !== 'DESC') {
    $order = 'DESC';
}

try {
    $db = getConnection();
    
    // Construir query
    $where = ["pc.status = 'active'"];
    $params = [];
    
    if ($creator_id) {
        $where[] = "pc.user_id = ?";
        $params[] = $creator_id;
    }
    
    if ($content_type) {
        $where[] = "pc.content_type = ?";
        $params[] = $content_type;
    }
    
    if ($min_price !== null) {
        $where[] = "pc.price >= ?";
        $params[] = $min_price;
    }
    
    if ($max_price !== null) {
        $where[] = "pc.price <= ?";
        $params[] = $max_price;
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Contar total
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM paywall_content pc
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener contenido
    $stmt = $db->prepare("
        SELECT * FROM v_paywall_content_full
        WHERE $where_clause
        ORDER BY $sort_by $order
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    
    $content = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular páginas
    $total_pages = ceil($total / $limit);
    $current_page = floor($offset / $limit) + 1;
    
    sendSuccess([
        'content' => $content,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'has_more' => $offset + $limit < $total
        ],
        'filters' => [
            'creator_id' => $creator_id,
            'content_type' => $content_type,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'sort_by' => $sort_by,
            'order' => $order
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in list_content: " . $e->getMessage());
    sendError('Database error', 500);
} catch (Exception $e) {
    error_log("Error in list_content: " . $e->getMessage());
    sendError('Server error', 500);
}
