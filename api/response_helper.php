<?php
// ============================================
// RESPONSE HELPER - Ensure valid JSON responses
// ============================================

/**
 * Send JSON response and exit
 * Ensures content-type is set and output is valid JSON
 */
function sendJsonResponse($data, $http_code = 200) {
    // Ensure no previous output
    if (ob_get_length()) {
        ob_clean();
    }

    // Set HTTP response code
    http_response_code($http_code);

    // Ensure content-type is JSON
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // Encode and send
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        // JSON encoding failed
        error_log("CRITICAL - JSON encoding failed: " . json_last_error_msg());
        $json = json_encode([
            'success' => false,
            'error' => 'json_encoding_error',
            'message' => 'Internal server error'
        ]);
    }

    echo $json;
    exit;
}

/**
 * Send success response
 */
function sendSuccess($data = [], $message = null, $http_code = 200) {
    $response = ['success' => true];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if (!empty($data)) {
        $response = array_merge($response, $data);
    }

    sendJsonResponse($response, $http_code);
}

/**
 * Send error response
 */
function sendError($message, $error_code = null, $http_code = 400, $additional_data = []) {
    $response = [
        'success' => false,
        'message' => $message
    ];

    if ($error_code !== null) {
        $response['error'] = $error_code;
    }

    if (!empty($additional_data)) {
        $response = array_merge($response, $additional_data);
    }

    sendJsonResponse($response, $http_code);
}

/**
 * Send validation error
 */
function sendValidationError($errors, $message = 'Validation failed') {
    sendError($message, 'validation_error', 422, ['errors' => $errors]);
}

/**
 * Send authentication error
 */
function sendAuthError($message = 'Authentication required') {
    sendError($message, 'authentication_required', 401);
}

/**
 * Send authorization error
 */
function sendAuthorizationError($message = 'Insufficient permissions') {
    sendError($message, 'insufficient_permissions', 403);
}

/**
 * Send not found error
 */
function sendNotFoundError($message = 'Resource not found') {
    sendError($message, 'not_found', 404);
}

/**
 * Send server error
 */
function sendServerError($message = 'Internal server error') {
    sendError($message, 'server_error', 500);
}

/**
 * Validate required fields in request
 * @param array $data Input data
 * @param array $required_fields Array of required field names
 * @return array|null Returns array of errors or null if valid
 */
function validateRequiredFields($data, $required_fields) {
    $errors = [];

    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $errors[$field] = "Field '$field' is required";
        }
    }

    return empty($errors) ? null : $errors;
}

/**
 * Sanitize array of data
 */
function sanitizeArray($data) {
    if (!is_array($data)) {
        return $data;
    }

    $sanitized = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = sanitizeArray($value);
        } else if (is_string($value)) {
            $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        } else {
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
}

?>