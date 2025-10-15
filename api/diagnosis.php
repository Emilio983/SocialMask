<?php
// ============================================
// thesocialmask DIAGNOSIS - Compatible with shared hosting
// Access via: /api/diagnosis.php?key=YOUR_SECRET_KEY
// ============================================

// Security: Require secret key from environment
$required_key = env('DIAGNOSIS_KEY', 'change_this_secret_key');
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $required_key) {
    http_response_code(403);
    die('Access denied');
}

// Disable output buffering for real-time output
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== thesocialmask SERVER DIAGNOSIS ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// 1. PHP Information
echo "1) PHP INFORMATION\n";
echo "==================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "Script Filename: " . __FILE__ . "\n\n";

// 2. PHP Extensions
echo "2) PHP EXTENSIONS\n";
echo "=================\n";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "$ext: " . ($loaded ? '✓ Loaded' : '✗ Missing') . "\n";
}
echo "\n";

// 3. PHP Settings
echo "3) PHP SETTINGS\n";
echo "===============\n";
$settings = [
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => ini_get('error_reporting'),
    'session.save_handler' => ini_get('session.save_handler'),
    'session.save_path' => ini_get('session.save_path'),
];
foreach ($settings as $key => $value) {
    echo "$key: $value\n";
}
echo "\n";

// 4. File Permissions
echo "4) FILE PERMISSIONS\n";
echo "===================\n";
$files_to_check = [
    __DIR__ . '/../config/connection.php',
    __DIR__ . '/../.env',
    __DIR__ . '/utils.php',
    __DIR__ . '/compatibility_functions.php',
];
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $readable = is_readable($file) ? '✓' : '✗';
        $writable = is_writable($file) ? '✓' : '✗';
        echo basename($file) . ": " . substr(sprintf('%o', $perms), -4) . " [R:$readable W:$writable]\n";
    } else {
        echo basename($file) . ": ✗ NOT FOUND\n";
    }
}
echo "\n";

// 5. Database Connection Test
echo "5) DATABASE CONNECTION\n";
echo "======================\n";
try {
    require_once __DIR__ . '/../config/connection.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "✓ PDO connection successful\n";
        $stmt = $pdo->query("SELECT VERSION()");
        $version = $stmt->fetchColumn();
        echo "MySQL Version: $version\n";

        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $count = $stmt->fetchColumn();
        echo "Users table: $count records\n";
    } else {
        echo "✗ PDO not available\n";
    }
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Session Test
echo "6) SESSION TEST\n";
echo "===============\n";
echo "Session status: " . session_status() . "\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✓ Session active\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Session name: " . session_name() . "\n";
} else {
    echo "Session not active\n";
}
echo "\n";

// 7. Error Log Check
echo "7) RECENT ERROR LOG\n";
echo "===================\n";
$error_log_path = __DIR__ . '/../error_log';
if (file_exists($error_log_path) && is_readable($error_log_path)) {
    $lines = file($error_log_path);
    $recent = array_slice($lines, -20);
    foreach ($recent as $line) {
        echo $line;
    }
} else {
    echo "Error log not found or not readable\n";
}
echo "\n";

// 8. Function Checks
echo "8) FUNCTION CHECKS\n";
echo "==================\n";
$functions = ['get_flash', 'verifySignature', 'isAuthenticated', 'sanitizeInput'];
foreach ($functions as $func) {
    echo "$func: " . (function_exists($func) ? '✓ Exists' : '✗ Missing') . "\n";
}
echo "\n";

// 9. Environment Variables
echo "9) ENVIRONMENT VARIABLES\n";
echo "========================\n";
$env_vars = ['DB_HOST', 'DB_NAME', 'APP_NAME', 'DEBUG_MODE'];
foreach ($env_vars as $var) {
    $value = env($var, 'NOT_SET');
    // Mask sensitive values
    if (strpos($var, 'PASS') !== false || strpos($var, 'SECRET') !== false) {
        $value = '****';
    }
    echo "$var: $value\n";
}
echo "\n";

// 10. Test API Endpoints
echo "10) API ENDPOINT TESTS\n";
echo "======================\n";

// Test check_session
try {
    $response = @file_get_contents(__DIR__ . '/check_session.php');
    $data = json_decode($response, true);
    if ($data) {
        echo "check_session.php: ✓ Returns valid JSON\n";
    } else {
        echo "check_session.php: ✗ Invalid JSON response\n";
    }
} catch (Exception $e) {
    echo "check_session.php: ✗ " . $e->getMessage() . "\n";
}

echo "\n";
echo "=== DIAGNOSIS COMPLETE ===\n";
echo date('Y-m-d H:i:s') . "\n";

?>