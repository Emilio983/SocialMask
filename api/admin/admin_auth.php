<?php
/**
 * ADMIN AUTHENTICATION & SECURITY
 * Include this file at the beginning of all admin APIs
 *
 * Usage:
 *   require_once __DIR__ . '/admin_auth.php';
 */

// Prevent direct access
if (!defined('ADMIN_API')) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Direct access not allowed']));
}

// Rate limiting - Max 30 admin actions per minute
require_once __DIR__ . '/../helpers/rate_limiter.php';
if (!isset($_SESSION['user_id'])) {
    // IP-based rate limiting for unauthenticated requests
    if (!checkRateLimit($_SERVER['REMOTE_ADDR'] . '_admin', 10, 60)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many requests. Please wait before trying again.'
        ]);
        exit;
    }
} else {
    // User-based rate limiting for authenticated admins
    if (!checkRateLimit('admin_' . $_SESSION['user_id'], 30, 60)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many admin actions. Please wait before trying again.'
        ]);
        exit;
    }
}

// Verify session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$admin_id = $_SESSION['user_id'];

// Verify admin role
try {
    $stmt = $pdo->prepare("SELECT role, username FROM users WHERE user_id = ? AND account_status = 'active'");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'User not found or inactive']);
        exit;
    }

    if ($admin['role'] !== 'admin') {
        // Log unauthorized access attempt
        try {
            $stmt_log = $pdo->prepare("
                INSERT INTO payment_logs (
                    user_id,
                    transaction_type,
                    action,
                    error_message,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (?, 'other', 'fail', 'Unauthorized admin access attempt', ?, ?, NOW())
            ");
            $stmt_log->execute([
                $admin_id,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log unauthorized access: " . $e->getMessage());
        }

        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    // Set admin info for use in the API
    $GLOBALS['admin_user'] = $admin;
    $GLOBALS['admin_id'] = $admin_id;

} catch (PDOException $e) {
    error_log("Admin auth error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Authentication error']);
    exit;
}

/**
 * Helper function to log admin actions
 */
function logAdminAction($action_type, $target_type = null, $target_id = null, $details = []) {
    global $pdo, $admin_id;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_actions (
                admin_id,
                action_type,
                target_type,
                target_id,
                action_details,
                ip_address,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $admin_id,
            $action_type,
            $target_type,
            $target_id,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to validate admin action types
 */
function isValidActionType($action_type) {
    $valid_types = ['update_setting', 'process_refund', 'blacklist_wallet', 'resolve_alert', 'manual_transaction'];
    return in_array($action_type, $valid_types);
}
?>
