<?php
/**
 * ============================================
 * GET NONCE FOR ADDRESS
 * ============================================
 * Endpoint: GET /api/governance/get-nonce.php
 * Returns current nonce for voter address
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sphera';

try {
    if (!isset($_GET['address'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing address parameter']);
        exit();
    }
    
    $address = strtolower($_GET['address']);
    
    // Validate address
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid address format']);
        exit();
    }
    
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Get or create nonce
    $stmt = $mysqli->prepare("
        INSERT INTO governance_nonces (address, current_nonce)
        VALUES (?, 0)
        ON DUPLICATE KEY UPDATE address = address
    ");
    $stmt->bind_param("s", $address);
    $stmt->execute();
    
    // Fetch current nonce
    $stmt = $mysqli->prepare("SELECT current_nonce FROM governance_nonces WHERE address = ?");
    $stmt->bind_param("s", $address);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $nonce = $row ? intval($row['current_nonce']) : 0;
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'nonce' => $nonce,
        'address' => $address
    ]);
    
    $mysqli->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
