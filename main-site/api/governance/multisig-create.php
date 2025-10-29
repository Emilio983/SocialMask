<?php
/**
 * ============================================
 * CREATE MULTISIG PROPOSAL
 * ============================================
 * Endpoint: POST /api/governance/multisig-create.php
 * Creates a new proposal requiring multi-signature approval
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
    $required = ['proposalId', 'proposalType', 'title', 'description', 'proposerAddress', 'targetContract'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            exit();
        }
    }
    
    $proposalId = intval($input['proposalId']);
    $proposalType = $input['proposalType'];
    $title = $input['title'];
    $description = $input['description'];
    $proposerAddress = strtolower($input['proposerAddress']);
    $targetContract = strtolower($input['targetContract']);
    $functionData = $input['functionData'] ?? '';
    $ethValue = $input['ethValue'] ?? '0';
    $durationDays = intval($input['durationDays'] ?? 7);
    
    // Validate addresses
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $proposerAddress)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid proposer address']);
        exit();
    }
    
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $targetContract)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid target contract']);
        exit();
    }
    
    // Validate proposal type
    $validTypes = ['TREASURY_WITHDRAWAL', 'PARAMETER_CHANGE', 'SIGNER_CHANGE', 'EMERGENCY_ACTION', 'CONTRACT_UPGRADE'];
    if (!in_array($proposalType, $validTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid proposal type']);
        exit();
    }
    
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Check if proposer is an authorized signer
    $stmt = $mysqli->prepare("SELECT is_active FROM governance_multisig_signers WHERE address = ?");
    $stmt->bind_param("s", $proposerAddress);
    $stmt->execute();
    $result = $stmt->get_result();
    $signer = $result->fetch_assoc();
    
    if (!$signer || !$signer['is_active']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only authorized signers can create proposals']);
        exit();
    }
    
    // Check for duplicate proposal ID
    $stmt = $mysqli->prepare("SELECT id FROM governance_multisig_proposals WHERE proposal_id = ?");
    $stmt->bind_param("i", $proposalId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Proposal ID already exists']);
        exit();
    }
    
    // Calculate expiration
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$durationDays days"));
    
    // Insert proposal
    $stmt = $mysqli->prepare("
        INSERT INTO governance_multisig_proposals (
            proposal_id, proposal_type, title, description, 
            proposer_address, target_contract, function_data, 
            eth_value, expires_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "issssssss",
        $proposalId,
        $proposalType,
        $title,
        $description,
        $proposerAddress,
        $targetContract,
        $functionData,
        $ethValue,
        $expiresAt
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create proposal: ' . $stmt->error);
    }
    
    // Update signer statistics
    $mysqli->query("
        UPDATE governance_multisig_signers 
        SET proposals_created = proposals_created + 1,
            last_activity_at = NOW()
        WHERE address = '$proposerAddress'
    ");
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'proposalId' => $proposalId,
        'expiresAt' => $expiresAt,
        'message' => 'Proposal created successfully'
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
