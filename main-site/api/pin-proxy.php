<?php
/**
 * ============================================
 * PIN PROXY - IPFS/Pinata Upload Endpoint
 * ============================================
 * Authenticated proxy for uploading encrypted files to Pinata
 * Only accepts encrypted blobs, never plain files
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../api/helpers/ipfs_helper.php';

// Rate limiting
session_start();
$rate_limit_key = 'pin_proxy_uploads_' . session_id();
$uploads_count = $_SESSION[$rate_limit_key] ?? 0;
$rate_limit = 10; // 10 uploads per session per hour

if ($uploads_count >= $rate_limit) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Rate limit exceeded. Maximum ' . $rate_limit . ' uploads per hour.'
    ]);
    exit;
}

try {
    // Verify authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    $user_id = $_SESSION['user_id'];

    // Check if file was uploaded
    if (!isset($_FILES['encrypted_file'])) {
        throw new Exception('No file uploaded', 400);
    }

    $file = $_FILES['encrypted_file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error'], 400);
    }

    // Check file size (50MB max default)
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size: 50MB', 400);
    }

    // Parse metadata
    $metadata_raw = $_POST['metadata'] ?? '{}';
    $metadata = json_decode($metadata_raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid metadata JSON', 400);
    }

    // Add system metadata
    $metadata['uploaded_by'] = $user_id;
    $metadata['uploaded_at'] = time();
    $metadata['encrypted'] = true;
    $metadata['source'] = 'socialmask';

    // Upload to Pinata
    $ipfs_result = IPFSHelper::uploadFile(
        $file['tmp_name'],
        'encrypted_' . uniqid() . '.bin',
        $metadata
    );

    if (!$ipfs_result['success']) {
        throw new Exception('IPFS upload failed: ' . $ipfs_result['message'], 500);
    }

    // Increment rate limit counter
    $_SESSION[$rate_limit_key] = $uploads_count + 1;

    // Log upload to database (optional)
    $stmt = $pdo->prepare("
        INSERT INTO ipfs_uploads (user_id, ipfs_hash, file_size, metadata, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $ipfs_result['ipfs_hash'],
        $file['size'],
        json_encode($metadata)
    ]);

    // Return success
    echo json_encode([
        'success' => true,
        'cid' => $ipfs_result['ipfs_hash'],
        'size' => $ipfs_result['size'],
        'gateway_url' => $ipfs_result['gateway_url'],
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 400 || $code >= 600) {
        $code = 500;
    }

    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $code
    ]);
}
