<?php
/**
 * ============================================
 * FILE INFO API
 * ============================================
 * Obtiene información básica de un archivo por CID
 * Retorna solo metadatos públicos del índice
 * 
 * GET /api/file-info.php?cid=QmXxxx
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
$cid = $_GET['cid'] ?? null;

if (!$cid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CID required']);
    exit;
}

try {
    // Obtener info del archivo con verificación de acceso
    $stmt = $pdo->prepare("
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
            fi.updated_at,
            fa.access_type,
            u.username as sender_name
        FROM file_index fi
        INNER JOIN file_access fa ON fi.cid = fa.cid
        LEFT JOIN usuarios u ON fi.sender_id = u.id
        WHERE fi.cid = ? AND fa.user_id = ?
    ");

    $stmt->execute([$cid, $current_user_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'File not found or access denied'
        ]);
        exit;
    }

    // Obtener lista de recipients (solo IDs, sin datos sensibles)
    $stmt = $pdo->prepare("
        SELECT 
            fa.user_id,
            fa.access_type,
            u.username
        FROM file_access fa
        LEFT JOIN usuarios u ON fa.user_id = u.id
        WHERE fa.cid = ?
        ORDER BY fa.access_type, fa.granted_at
    ");
    $stmt->execute([$cid]);
    $access_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'file' => [
            'cid' => $file['cid'],
            'file_name' => $file['file_name'],
            'file_type' => $file['file_type'],
            'file_size' => (int)$file['file_size'],
            'sender_id' => (int)$file['sender_id'],
            'sender_name' => $file['sender_name'],
            'recipient_count' => (int)$file['recipient_count'],
            'has_thumbnail' => (bool)$file['has_thumbnail'],
            'thumbnail_cid' => $file['thumbnail_cid'],
            'created_at' => $file['created_at'],
            'updated_at' => $file['updated_at'],
            'your_access_type' => $file['access_type']
        ],
        'access_list' => array_map(function($row) {
            return [
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'],
                'access_type' => $row['access_type']
            ];
        }, $access_list),
        'ipfs_gateway' => "https://gateway.pinata.cloud/ipfs/{$cid}",
        'message' => 'Use P2P system to retrieve encrypted metadata (IV, wrapped keys) with: p2pMetadata.getMetadata(cid)'
    ]);

} catch (PDOException $e) {
    error_log("File info error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
