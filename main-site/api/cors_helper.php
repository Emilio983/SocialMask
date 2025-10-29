<?php
// ============================================
// CORS HELPER - Secure Cross-Origin configuration
// ============================================

/**
 * Configure CORS headers securely
 * Reads allowed origins from environment variables
 */
function configureCORS() {
    // Get request origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Get allowed origins from environment (use defined constant or default to *)
    $allowed_origins_str = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : '*';
    $allow_credentials = defined('CORS_ALLOW_CREDENTIALS') ? CORS_ALLOW_CREDENTIALS : false;

    // If wildcard is allowed (development only)
    if ($allowed_origins_str === '*') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        if (DEBUG) {
            error_log("WARNING - CORS: Wildcard (*) origin allowed. This should only be used in development!");
        }
        return;
    }

    // Parse allowed origins
    $allowed_origins = array_map('trim', explode(',', $allowed_origins_str));

    // Check if origin is allowed
    if (in_array($origin, $allowed_origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        if ($allow_credentials) {
            header('Access-Control-Allow-Credentials: true');
        }
    } else {
        // Origin not allowed
        if (!empty($origin) && DEBUG) {
            error_log("INFO - CORS: Origin '$origin' not in allowed list: $allowed_origins_str");
        }

        // Don't send CORS headers for disallowed origins
        // This will cause CORS errors in the browser, which is intentional
    }
}

/**
 * Handle preflight OPTIONS request
 */
function handleCORSPreflight() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        configureCORS();
        header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours
        http_response_code(204); // No Content
        exit;
    }
}

/**
 * Check if origin is allowed
 * @param string $origin Origin to check
 * @return bool
 */
function isOriginAllowed($origin) {
    $allowed_origins_str = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : '*';

    if ($allowed_origins_str === '*') {
        return true;
    }

    $allowed_origins = array_map('trim', explode(',', $allowed_origins_str));
    return in_array($origin, $allowed_origins, true);
}

/**
 * Get list of allowed origins
 * @return array
 */
function getAllowedOrigins() {
    $allowed_origins_str = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : '*';

    if ($allowed_origins_str === '*') {
        return ['*'];
    }

    return array_map('trim', explode(',', $allowed_origins_str));
}

/**
 * Handle CORS - Alias for configureCORS + handleCORSPreflight
 * Used by P2P and other APIs
 */
function handleCORS() {
    configureCORS();
    handleCORSPreflight();
}

?>