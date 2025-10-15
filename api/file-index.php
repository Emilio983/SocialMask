<?php
/**
 * ============================================
 * FILE INDEX API
 * ============================================
 * Indexa archivos en MySQL (solo metadatos públicos)
 * NO almacena claves, IV ni datos sensibles
 * 
 * POST /api/file-index.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/connection.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$user_id = $_SESSION['user_id'];

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);

$cid = $input['cid'] ?? null;
$file_name = $input['file_name'] ?? null;
$file_type = $input['file_type'] ?? null;
$file_size = $input['file_size'] ?? null;
$recipients = $input['recipients'] ?? [];
$thumbnail_cid = $input['thumbnail_cid'] ?? null;

// Validar datos requeridos
if (!$cid || !$file_name || !$file_type || !$file_size) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: cid, file_name, file_type, file_size'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insertar en índice (solo metadatos públicos)
    $stmt = $pdo->prepare("
        INSERT INTO file_index 
        (cid, file_name, file_type, file_size, sender_id, recipient_count, has_thumbnail, thumbnail_cid)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            file_name = VALUES(file_name),
            file_type = VALUES(file_type),
            file_size = VALUES(file_size),
            recipient_count = VALUES(recipient_count),
            has_thumbnail = VALUES(has_thumbnail),
            thumbnail_cid = VALUES(thumbnail_cid),
            updated_at = CURRENT_TIMESTAMP
    ");

    $recipient_count = count($recipients);
    $has_thumbnail = !empty($thumbnail_cid);

    $stmt->execute([
        $cid,
        $file_name,
        $file_type,
        $file_size,
        $user_id,
        $recipient_count,
        $has_thumbnail,
        $thumbnail_cid
    ]);

    // Registrar acceso del sender
    $stmt = $pdo->prepare("
        INSERT INTO file_access (cid, user_id, access_type)
        VALUES (?, ?, 'sender')
        ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$cid, $user_id]);

    // Registrar acceso de recipients
    if (!empty($recipients)) {
        $stmt = $pdo->prepare("
            INSERT INTO file_access (cid, user_id, access_type)
            VALUES (?, ?, 'recipient')
            ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP
        ");

        foreach ($recipients as $recipient_id) {
            $stmt->execute([$cid, $recipient_id]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'File indexed successfully',
        'cid' => $cid,
        'indexed_at' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("File index error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
