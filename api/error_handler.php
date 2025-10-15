<?php
// ============================================
// API ERROR HANDLER
// Custom error handler that returns JSON responses
// ============================================

// Prevent any output before headers
ob_clean();

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Get the HTTP status code
$status_code = http_response_code() ?: 500;

// Determine error message based on status code
switch ($status_code) {
    case 404:
        $message = 'API endpoint not found';
        break;
    case 500:
        $message = 'Internal server error';
        break;
    default:
        $message = 'An error occurred';
        break;
}

// Log the error
error_log("API Error Handler: Status $status_code - " . $_SERVER['REQUEST_URI'] ?? 'unknown');

// Return JSON error response
echo json_encode([
    'success' => false,
    'error' => $message,
    'status_code' => $status_code,
    'timestamp' => date('c')
]);

exit;
?>