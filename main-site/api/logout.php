<?php
// ============================================
// LOGOUT - Cerrar sesión de usuario
// ============================================

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once '../config/connection.php';

try {
    // Log the logout if user is authenticated
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'] ?? 'unknown';

        // Log the logout
        error_log("User logged out: $username (ID: $user_id)");

        // Log activity if function exists
        if (file_exists('utils.php')) {
            require_once 'utils.php';
            if (function_exists('logActivity') && isset($pdo)) {
                logActivity($pdo, $user_id, 'logout');
            }
        }
    }

    // Clear all session variables
    $_SESSION = array();

    // Delete the session cookie
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy the session completely
    session_destroy();

    // Start a new clean session
    session_start();

    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);

} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error during logout'
    ]);
}
?>