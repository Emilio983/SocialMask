<?php
/**
 * API: UPLOAD IMAGE
 * Upload logo or banner for communities, profiles, posts
 *
 * POST /api/upload_image.php
 * Content-Type: multipart/form-data
 * Body:
 *   - file: Image file
 *   - type: 'community_logo', 'community_banner', 'profile_pic', 'profile_cover', 'post_image'
 */

require_once __DIR__ . '/../config/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

// Get upload type
$upload_type = $_POST['type'] ?? 'post_image';

// Validate type
$valid_types = ['community_logo', 'community_banner', 'profile_pic', 'profile_cover', 'post_image'];
if (!in_array($upload_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid upload type']);
    exit;
}

// Configuration based on type
$config = [
    'community_logo' => ['max_size' => 2 * 1024 * 1024, 'folder' => 'communities/logos'], // 2MB
    'community_banner' => ['max_size' => 5 * 1024 * 1024, 'folder' => 'communities/banners'], // 5MB
    'profile_pic' => ['max_size' => 2 * 1024 * 1024, 'folder' => 'profiles/pictures'], // 2MB
    'profile_cover' => ['max_size' => 5 * 1024 * 1024, 'folder' => 'profiles/covers'], // 5MB
    'post_image' => ['max_size' => 10 * 1024 * 1024, 'folder' => 'posts/images'] // 10MB
];

$max_size = $config[$upload_type]['max_size'];
$upload_folder = $config[$upload_type]['folder'];

$file = $_FILES['file'];
$file_size = $file['size'];
$file_tmp = $file['tmp_name'];
$file_name = $file['name'];
$file_error = $file['error'];

// Validate file size
if ($file_size > $max_size) {
    $max_mb = $max_size / (1024 * 1024);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "File too large. Max size: {$max_mb}MB"]);
    exit;
}

// Validate file type (MIME type)
$allowed_mime_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_tmp);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_mime_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images allowed (jpg, png, gif, webp)']);
    exit;
}

// Validate file extension
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($file_ext, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file extension']);
    exit;
}

// Additional security: Check image dimensions
$image_info = getimagesize($file_tmp);
if ($image_info === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File is not a valid image']);
    exit;
}

// Check if image is not too small (min 100x100 for logos, 400x200 for banners)
$min_width = ($upload_type === 'community_banner' || $upload_type === 'profile_cover') ? 400 : 100;
$min_height = ($upload_type === 'community_banner' || $upload_type === 'profile_cover') ? 200 : 100;

if ($image_info[0] < $min_width || $image_info[1] < $min_height) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => "Image too small. Minimum size: {$min_width}x{$min_height}px"
    ]);
    exit;
}

try {
    // Create uploads directory structure if not exists
    $base_upload_dir = __DIR__ . '/../uploads/';
    $full_upload_dir = $base_upload_dir . $upload_folder;

    if (!file_exists($full_upload_dir)) {
        mkdir($full_upload_dir, 0755, true);
    }

    // Generate unique filename
    $unique_name = uniqid('img_' . $user_id . '_', true) . '.' . $file_ext;
    $destination = $full_upload_dir . '/' . $unique_name;

    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $destination)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Generate URL path (relative to web root)
    $url_path = '/uploads/' . $upload_folder . '/' . $unique_name;

    // Log upload in database (optional tracking)
    $stmt = $pdo->prepare("
        INSERT INTO file_uploads (user_id, file_type, file_path, file_size, mime_type, upload_type, width, height, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $file_ext,
        $url_path,
        $file_size,
        $mime_type,
        $upload_type,
        $image_info[0], // width
        $image_info[1]  // height
    ]);

    $upload_id = $pdo->lastInsertId();

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'data' => [
            'upload_id' => $upload_id,
            'url' => $url_path,
            'filename' => $unique_name,
            'size' => $file_size,
            'type' => $mime_type,
            'dimensions' => [
                'width' => $image_info[0],
                'height' => $image_info[1]
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to upload image'
    ]);
}
?>
