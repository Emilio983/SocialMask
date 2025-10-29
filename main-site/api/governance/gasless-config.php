<?php
/**
 * ============================================
 * GASLESS VOTING CONFIGURATION
 * ============================================
 * Endpoint: GET /api/governance/gasless-config.php
 * Returns gasless voting system configuration
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sphera';

try {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Fetch configuration
    $result = $mysqli->query("SELECT config_key, config_value FROM governance_relayer_config");
    
    $config = [];
    while ($row = $result->fetch_assoc()) {
        $config[$row['config_key']] = $row['config_value'];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'config' => $config,
        'enabled' => ($config['enable_gasless_voting'] ?? 'false') === 'true'
    ]);
    
    $mysqli->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
