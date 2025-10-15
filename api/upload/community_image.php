<?php
// ============================================
// COMMUNITY IMAGE UPLOAD API
// POST /api/upload/community_image.php
// ============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../config/connection.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['file'];
    $upload_type = $_POST['upload_type'] ?? 'logo'; // logo or banner

    // Validate upload type
    if (!in_array($upload_type, ['logo', 'banner'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid upload type']);
        exit;
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
        exit;
    }

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed']);
        exit;
    }

    // Get file extension
    $extension = match($mime_type) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../../uploads/communities/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $unique_name = uniqid($user_id . '_' . $upload_type . '_', true) . '.' . $extension;
    $file_path = $upload_dir . $unique_name;
    $relative_path = '/uploads/communities/' . $unique_name;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
        exit;
    }

    // Optimize image based on type
    try {
        if ($upload_type === 'logo') {
            // Resize logo to max 200x200 while maintaining aspect ratio
            resizeImage($file_path, $file_path, 200, 200, $mime_type);
        } elseif ($upload_type === 'banner') {
            // Resize banner to max 1200x400 while maintaining aspect ratio
            resizeImage($file_path, $file_path, 1200, 400, $mime_type);
        }
    } catch (Exception $e) {
        error_log("Image optimization failed: " . $e->getMessage());
        // Continue even if optimization fails
    }

    // Record upload in database
    $insert_sql = "
        INSERT INTO community_uploads (user_id, upload_type, file_path, file_name, file_size, mime_type, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute([
        $user_id,
        $upload_type,
        $relative_path,
        $file['name'],
        $file['size'],
        $mime_type
    ]);

    $upload_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'upload' => [
            'id' => (int)$upload_id,
            'file_path' => $relative_path,
            'file_name' => $file['name'],
            'file_size' => (int)$file['size'],
            'mime_type' => $mime_type,
            'upload_type' => $upload_type
        ]
    ]);

} catch (Exception $e) {
    error_log("ERROR - upload/community_image.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

// Helper function to resize images
function resizeImage($source, $destination, $max_width, $max_height, $mime_type) {
    list($orig_width, $orig_height) = getimagesize($source);

    // Calculate new dimensions while maintaining aspect ratio
    $ratio = min($max_width / $orig_width, $max_height / $orig_height);

    // Only resize if image is larger than max dimensions
    if ($ratio >= 1) {
        return; // Image is already small enough
    }

    $new_width = (int)($orig_width * $ratio);
    $new_height = (int)($orig_height * $ratio);

    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);

    // Preserve transparency for PNG and GIF
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
        imagefill($new_image, 0, 0, $transparent);
    }

    // Load source image
    $source_image = match($mime_type) {
        'image/jpeg', 'image/jpg' => imagecreatefromjpeg($source),
        'image/png' => imagecreatefrompng($source),
        'image/gif' => imagecreatefromgif($source),
        'image/webp' => imagecreatefromwebp($source),
        default => imagecreatefromjpeg($source)
    };

    // Resize
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

    // Save
    match($mime_type) {
        'image/jpeg', 'image/jpg' => imagejpeg($new_image, $destination, 90),
        'image/png' => imagepng($new_image, $destination, 9),
        'image/gif' => imagegif($new_image, $destination),
        'image/webp' => imagewebp($new_image, $destination, 90),
        default => imagejpeg($new_image, $destination, 90)
    };

    // Free memory
    imagedestroy($source_image);
    imagedestroy($new_image);
}
?>