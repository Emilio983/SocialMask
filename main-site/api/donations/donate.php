<?php
require_once '../../config/connection.php';
require_once '../helpers/TokenValidator.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Validar JWT
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

try {
    $decoded = TokenValidator::validate($token);
    if (!$decoded) {
        throw new Exception('Invalid token');
    }
    $userId = $decoded->user_id;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);

$campaignId = $input['campaign_id'] ?? 0;
$amount = $input['amount'] ?? 0;
$txHash = $input['tx_hash'] ?? '';
$donorAddress = $input['donor_address'] ?? '';
$message = $input['message'] ?? '';

// Validar datos
if ($campaignId <= 0 || $amount <= 0 || empty($txHash) || empty($donorAddress)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Validar formato de txHash
if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid transaction hash']);
    exit;
}

// Validar formato de address
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $donorAddress)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid wallet address']);
    exit;
}

try {
    // Verificar que la campaña existe y está activa
    $stmt = $conn->prepare("SELECT * FROM donation_campaigns WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $campaign = $stmt->get_result()->fetch_assoc();
    
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Campaign not found or not active']);
        exit;
    }
    
    // Verificar que el txHash no existe ya
    $stmt = $conn->prepare("SELECT id FROM donations WHERE tx_hash = ?");
    $stmt->bind_param("s", $txHash);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Transaction already recorded']);
        exit;
    }
    
    // Insertar donación con estado pending
    $stmt = $conn->prepare("
        INSERT INTO donations 
        (campaign_id, user_id, donor_address, amount, tx_hash, message, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->bind_param(
        "iisdss", 
        $campaignId, 
        $userId, 
        $donorAddress, 
        $amount, 
        $txHash,
        $message
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to record donation: ' . $stmt->error);
    }
    
    $donationId = $conn->insert_id;
    
    // Obtener la donación creada
    $stmt = $conn->prepare("
        SELECT d.*, u.username
        FROM donations d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.id = ?
    ");
    $stmt->bind_param("i", $donationId);
    $stmt->execute();
    $donation = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'donation' => $donation,
        'message' => 'Donation recorded. Waiting for blockchain confirmation.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
