<?php
/**
 * ============================================
 * FILE SEARCH API
 * ============================================
 * Busca archivos en el índice MySQL
 * Retorna solo CIDs - el cliente debe obtener metadatos completos desde P2P
 * 
 * GET /api/file-search.php?q=query&type=image&user=123&limit=20&offset=0
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/connection.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Parámetros de búsqueda
$query = $_GET['q'] ?? '';
$file_type = $_GET['type'] ?? '';
$sender_id = $_GET['user'] ?? null;
$limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100
$offset = max((int)($_GET['offset'] ?? 0), 0);
$sort_by = $_GET['sort'] ?? 'created_at'; // created_at, file_name, file_size
$sort_order = strtoupper($_GET['order'] ?? 'DESC');

// Validar sort order
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

// Validar sort by
$allowed_sorts = ['created_at', 'file_name', 'file_size', 'updated_at'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}

try {
    // Construir query - solo archivos con acceso
    $sql = "
        SELECT 
            fi.cid,
            fi.file_name,
            fi.file_type,
            fi.file_size,
            fi.sender_id,
            fi.recipient_count,
            fi.has_thumbnail,
            fi.thumbnail_cid,
            fi.created_at,
            fa.access_type
        FROM file_index fi
        INNER JOIN file_access fa ON fi.cid = fa.cid
        WHERE fa.user_id = ?
    ";

    $params = [$current_user_id];

    // Filtro de texto (búsqueda FULLTEXT)
    if (!empty($query)) {
        $sql .= " AND MATCH(fi.file_name) AGAINST(? IN NATURAL LANGUAGE MODE)";
        $params[] = $query;
    }

    // Filtro por tipo de archivo
    if (!empty($file_type)) {
        $sql .= " AND fi.file_type LIKE ?";
        $params[] = $file_type . '%';
    }

    // Filtro por sender
    if ($sender_id !== null) {
        $sql .= " AND fi.sender_id = ?";
        $params[] = $sender_id;
    }

    // Ordenar
    $sql .= " ORDER BY fi.{$sort_by} {$sort_order}";

    // Limit y offset
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar total (para paginación)
    $count_sql = "
        SELECT COUNT(*) as total
        FROM file_index fi
        INNER JOIN file_access fa ON fi.cid = fa.cid
        WHERE fa.user_id = ?
    ";
    
    $count_params = [$current_user_id];
    
    if (!empty($query)) {
        $count_sql .= " AND MATCH(fi.file_name) AGAINST(? IN NATURAL LANGUAGE MODE)";
        $count_params[] = $query;
    }
    
    if (!empty($file_type)) {
        $count_sql .= " AND fi.file_type LIKE ?";
        $count_params[] = $file_type . '%';
    }
    
    if ($sender_id !== null) {
        $count_sql .= " AND fi.sender_id = ?";
        $count_params[] = $sender_id;
    }

    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Formatear resultados
    $files = array_map(function($row) {
        return [
            'cid' => $row['cid'],
            'file_name' => $row['file_name'],
            'file_type' => $row['file_type'],
            'file_size' => (int)$row['file_size'],
            'sender_id' => (int)$row['sender_id'],
            'recipient_count' => (int)$row['recipient_count'],
            'has_thumbnail' => (bool)$row['has_thumbnail'],
            'thumbnail_cid' => $row['thumbnail_cid'],
            'created_at' => $row['created_at'],
            'access_type' => $row['access_type'],
            // NO incluir IV, claves, ni datos sensibles
            // El cliente debe obtener esto desde P2P con verificación de acceso
            'p2p_required' => true // Indica que debe obtener metadatos completos desde P2P
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'files' => $files,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ],
        'message' => 'Use P2P system to retrieve full encrypted metadata (IV, wrapped keys) for each CID'
    ]);

} catch (PDOException $e) {
    error_log("File search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
