<?php
/**
 * ============================================
 * SIGN MULTISIG PROPOSAL
 * ============================================
 * Endpoint: POST /api/governance/multisig-sign.php
 * Add signature to a pending proposal
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sphera';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['proposalId']) || !isset($input['signerAddress']) || !isset($input['signature'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    $proposalId = intval($input['proposalId']);
    $signerAddress = strtolower($input['signerAddress']);
    $signature = $input['signature'];
    $messageHash = $input['messageHash'] ?? '';
    
    // Validate address
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $signerAddress)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid signer address']);
        exit();
    }
    
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Check if signer is authorized
    $stmt = $mysqli->prepare("SELECT is_active FROM governance_multisig_signers WHERE address = ?");
    $stmt->bind_param("s", $signerAddress);
    $stmt->execute();
    $result = $stmt->get_result();
    $signer = $result->fetch_assoc();
    
    if (!$signer || !$signer['is_active']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not an authorized signer']);
        exit();
    }
    
    // Get proposal details
    $stmt = $mysqli->prepare("
        SELECT status, expires_at, signature_count, required_signatures 
        FROM governance_multisig_proposals 
        WHERE proposal_id = ?
    ");
    $stmt->bind_param("i", $proposalId);
    $stmt->execute();
    $result = $stmt->get_result();
    $proposal = $result->fetch_assoc();
    
    if (!$proposal) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Proposal not found']);
        exit();
    }
    
    if ($proposal['status'] !== 'PENDING') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Proposal is not pending']);
        exit();
    }
    
    if (strtotime($proposal['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Proposal has expired']);
        exit();
    }
    
    // Check if already signed
    $stmt = $mysqli->prepare("
        SELECT id FROM governance_multisig_signatures 
        WHERE proposal_id = ? AND signer_address = ? AND is_revoked = FALSE
    ");
    $stmt->bind_param("is", $proposalId, $signerAddress);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Already signed this proposal']);
        exit();
    }
    
    // Add signature
    $stmt = $mysqli->prepare("
        INSERT INTO governance_multisig_signatures (
            proposal_id, signer_address, signature, message_hash
        ) VALUES (?, ?, ?, ?)
    ");
    
    $stmt->bind_param("isss", $proposalId, $signerAddress, $signature, $messageHash);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add signature: ' . $stmt->error);
    }
    
    // Update proposal signature count
    $newCount = $proposal['signature_count'] + 1;
    $newStatus = $newCount >= $proposal['required_signatures'] ? 'APPROVED' : 'PENDING';
    
    $mysqli->query("
        UPDATE governance_multisig_proposals 
        SET signature_count = $newCount,
            status = '$newStatus'
        WHERE proposal_id = $proposalId
    ");
    
    // Update signer statistics
    $mysqli->query("
        UPDATE governance_multisig_signers 
        SET signatures_given = signatures_given + 1,
            last_activity_at = NOW()
        WHERE address = '$signerAddress'
    ");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'signatureCount' => $newCount,
        'requiredSignatures' => $proposal['required_signatures'],
        'approved' => $newStatus === 'APPROVED',
        'message' => 'Signature added successfully'
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
