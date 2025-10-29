<?php
/**
 * API: UPLOAD TO IPFS
 * Upload files to IPFS via Pinata
 *
 * POST /api/upload/ipfs.php
 * Content-Type: multipart/form-data
 * Body:
 *   - file: File to upload
 *   - type: 'image', 'video', 'document' (optional)
 *   - metadata: JSON string with additional metadata (optional)
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../helpers/ipfs_helper.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'You must be logged in to upload files'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'message' => 'Use POST method'
    ]);
    exit;
}

// Verificar que se subió un archivo
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No file uploaded',
        'message' => 'Please provide a file to upload'
    ]);
    exit;
}

$file = $_FILES['file'];
$file_type = $_POST['type'] ?? 'image';
$metadata_json = $_POST['metadata'] ?? '{}';

// Validar tipo de archivo
$valid_types = ['image', 'video', 'document', 'json'];
if (!in_array($file_type, $valid_types)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid file type',
        'message' => 'Allowed types: ' . implode(', ', $valid_types)
    ]);
    exit;
}

// Configuración de límites por tipo
$limits = [
    'image' => ['max_size' => 10 * 1024 * 1024, 'mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']],
    'video' => ['max_size' => 50 * 1024 * 1024, 'mime_types' => ['video/mp4', 'video/webm', 'video/ogg']],
    'document' => ['max_size' => 5 * 1024 * 1024, 'mime_types' => ['application/pdf', 'text/plain']],
    'json' => ['max_size' => 1 * 1024 * 1024, 'mime_types' => ['application/json']]
];

$max_size = $limits[$file_type]['max_size'];
$allowed_mimes = $limits[$file_type]['mime_types'];

// Validar tamaño
if ($file['size'] > $max_size) {
    $max_mb = $max_size / (1024 * 1024);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'File too large',
        'message' => "Maximum file size for {$file_type}: {$max_mb}MB"
    ]);
    exit;
}

// Validar MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_mimes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid file format',
        'message' => 'Allowed formats for ' . $file_type . ': ' . implode(', ', $allowed_mimes)
    ]);
    exit;
}

// Parsear metadata adicional
$metadata = [];
try {
    $metadata = json_decode($metadata_json, true) ?: [];
} catch (Exception $e) {
    // Metadata opcional, continuar si falla
}

// Agregar metadata del usuario
$metadata = array_merge($metadata, [
    'user_id' => $user_id,
    'file_type' => $file_type,
    'mime_type' => $mime_type,
    'original_name' => $file['name']
]);

try {
    // Subir a IPFS vía Pinata
    $result = IPFSHelper::uploadFile(
        $file['tmp_name'],
        $file['name'],
        $metadata
    );
    
    // Log del upload en base de datos (opcional)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ipfs_uploads (user_id, ipfs_hash, file_name, file_type, file_size, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $result['hash'],
            $file['name'],
            $file_type,
            $result['size'],
            json_encode($metadata)
        ]);
    } catch (PDOException $e) {
        // Tabla opcional, no fallar si no existe
        error_log("IPFS upload log failed: " . $e->getMessage());
    }
    
    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded to IPFS successfully',
        'data' => [
            'ipfs_hash' => $result['hash'],
            'cid' => $result['cid'],
            'url' => $result['url'],
            'gateway_url' => $result['url'],
            'size' => $result['size'],
            'file_name' => $file['name'],
            'file_type' => $file_type,
            'mime_type' => $mime_type,
            'timestamp' => $result['timestamp']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("IPFS upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Upload failed',
        'message' => $e->getMessage()
    ]);
}
?>
