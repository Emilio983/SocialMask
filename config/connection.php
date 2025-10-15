<?php
declare(strict_types=1);

// ============================================
// THE SOCIAL MASK DATABASE CONNECTION
// Archivo de conexión centralizada a la base de datos
// ============================================

use TheSocialMask\Config\Env;

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/constants.php';

Env::load();

// Required base configuration
define('SESSION_SECRET', Env::require('SESSION_SECRET'));

// Database configuration
define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_NAME', Env::get('DB_NAME', 'thesocialmask'));
define('DB_USER', Env::get('DB_USER', 'root'));
define('DB_PASS', Env::get('DB_PASS', ''));
define('DB_CHARSET', Env::get('DB_CHARSET', 'utf8mb4'));

// Application settings
define('APP_NAME', Env::get('APP_NAME', 'TheSocialMask'));
define('APP_VERSION', Env::get('APP_VERSION', '1.0.0'));
define('JWT_SECRET', Env::get('JWT_SECRET', 'thesocialmask_secret_key_2024_' . DB_NAME));
define('SESSION_NAME', Env::get('SESSION_NAME', 'thesocialmask_session'));
define('NONCE_EXPIRY', Env::int('NONCE_EXPIRY', 300)); // 5 minutos
define('SESSION_LIFETIME', Env::int('SESSION_LIFETIME', 86400)); // 24 horas
define('DEBUG', Env::bool('DEBUG_MODE', false));

// PHP error handling configuration
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ============================================
// SESSION CONFIGURATION (Must be BEFORE session_start)
// ============================================

// Only configure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookies for production usage
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $isSecureContext = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');

    ini_set('session.cookie_secure', $isSecureContext ? '1' : '0');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecureContext,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(SESSION_LIFETIME, '/', '', $isSecureContext, true);
    }

    // Start session with configured name
    session_name(SESSION_NAME);
    session_start();
}

// Attempt database connection with enhanced error handling
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET . ' COLLATE utf8mb4_unicode_ci',
    ]);

    $pdo->exec("SET time_zone = '+00:00'");
} catch (PDOException $e) {
    error_log('CRITICAL - Database connection failed: ' . $e->getMessage());

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $is_api_request = strpos($script_name, '/api/') !== false ||
        strpos($request_uri, '/api/') !== false ||
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    http_response_code(500);

    if ($is_api_request) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Database connection error. Please try again later.',
        ]);
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Database Error - The Social Mask</title>
            <style>
                body { font-family: Arial, sans-serif; background: #0D1117; color: #C9D1D9; text-align: center; padding: 50px; }
                .error-container { max-width: 500px; margin: 0 auto; background: #161B22; padding: 40px; border-radius: 12px; border: 1px solid #30363D; }
                h1 { color: #ff6b6b; margin-bottom: 20px; }
                p { margin-bottom: 30px; line-height: 1.6; }
                a { color: #3B82F6; text-decoration: none; font-weight: bold; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1>⚠️ Database Connection Error</h1>
                <p>We're experiencing technical difficulties connecting to our database. Please try again in a few moments.</p>
                <a href='/'>← Return to Homepage</a>
            </div>
        </body>
        </html>";
    }
    exit;
}

function getConnection()
{
    global $pdo;
    return $pdo;
}

function testConnection(): bool
{
    global $pdo;
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        error_log('Connection test failed: ' . $e->getMessage());
        return false;
    }
}

function closeConnection(): void
{
    global $pdo;
    $pdo = null;
}

$compatibility_file = __DIR__ . '/../api/compatibility_functions.php';
if (file_exists($compatibility_file)) {
    require_once $compatibility_file;
} else {
    error_log('WARNING - Compatibility functions file not found: ' . $compatibility_file);
}

$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$is_api_request = strpos($script_name, '/api/') !== false || strpos($request_uri, '/api/') !== false;

if ($is_api_request && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
