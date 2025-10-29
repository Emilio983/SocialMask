<?php
// ============================================
// THE SOCIAL MASK CONFIGURATION
// Configuración general de la aplicación
// ============================================

// Incluir conexión a la base de datos
require_once __DIR__ . '/connection.php';

// Verificar que la conexión esté disponible
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("PDO connection not available in config.php");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

// Funciones de utilidad para la configuración
function isDevelopment() {
    return defined('DEBUG') && DEBUG === true;
}

function getAppUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . $host;
}

function logInfo($message) {
    if (isDevelopment()) {
        error_log("[INFO] " . $message);
    }
}

function logError($message) {
    error_log("[ERROR] " . $message);
}

// Log de configuración cargada
logInfo("TheSocialMask config loaded successfully");
?>