<?php
// ============================================
// REGISTER API
// ============================================

// IMPORTANTE: Headers PRIMERO, antes de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Configuración de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Legacy wallet registration está deshabilitado. Usa el flujo de passkeys.',
]);
?>
