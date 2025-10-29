<?php
/**
 * CSRF PROTECTION
 * Generate and validate CSRF tokens for forms
 *
 * Usage:
 *
 * In your form/page:
 *   $csrf_token = generateCSRFToken();
 *   <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
 *
 * In your API endpoint:
 *   require_once __DIR__ . '/helpers/csrf_protection.php';
 *   if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
 *       http_response_code(403);
 *       echo json_encode(['error' => 'Invalid CSRF token']);
 *       exit;
 *   }
 */

/**
 * Generate CSRF token and store in session
 *
 * @return string CSRF token
 */
function generateCSRFToken() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Generate token if not exists or expired
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    } else {
        // Regenerate token if older than 1 hour
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 *
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if token exists in session
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    // Validate token
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    // Check if token is not expired (2 hours max)
    if (isset($_SESSION['csrf_token_time'])) {
        if (time() - $_SESSION['csrf_token_time'] > 7200) {
            return false;
        }
    }

    return true;
}

/**
 * Require valid CSRF token or exit with error
 *
 * @param string|null $token Token from request (if null, checks $_POST['csrf_token'])
 */
function requireCSRFToken($token = null) {
    if ($token === null) {
        // Try to get from POST or JSON body
        if (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['csrf_token'] ?? '';
        }
    }

    if (!validateCSRFToken($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired CSRF token. Please refresh the page and try again.'
        ]);
        exit;
    }
}

/**
 * Get CSRF token HTML input field
 *
 * @return string HTML input element
 */
function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Get CSRF token for AJAX requests (JSON format)
 *
 * @return array Array with token
 */
function getCSRFTokenJSON() {
    return ['csrf_token' => generateCSRFToken()];
}
?>
