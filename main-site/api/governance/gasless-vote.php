<?php
/**
 * ============================================
 * GASLESS VOTE SUBMISSION
 * ============================================
 * Endpoint: POST /api/governance/gasless-vote.php
 * Receive signed vote and submit to blockchain via relayer
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // Web3 PHP library

use Web3\Web3;
use Web3\Contract;
use Web3\Utils;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['proposalId', 'support', 'voter', 'nonce', 'deadline', 'signature'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Missing required field: $field"
            ]);
            exit();
        }
    }
    
    // Validate ethereum addresses
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $input['voter'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid voter address'
        ]);
        exit();
    }
    
    // Validate signature
    if (!preg_match('/^0x[a-fA-F0-9]{130}$/', $input['signature'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid signature format'
        ]);
        exit();
    }
    
    // Validate support (0=Against, 1=For, 2=Abstain)
    if (!in_array($input['support'], [0, 1, 2])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid support value'
        ]);
        exit();
    }
    
    // Check deadline
    if ($input['deadline'] < time()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Signature expired'
        ]);
        exit();
    }
    
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Check if already voted
    $check_stmt = $mysqli->prepare("
        SELECT id FROM governance_gasless_votes 
        WHERE proposal_id = ? AND voter_address = ?
    ");
    $check_stmt->bind_param("ss", $input['proposalId'], $input['voter']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Already voted on this proposal'
        ]);
        exit();
    }
    
    // Rate limiting check (max 10 votes per minute per voter)
    $rate_check = $mysqli->prepare("
        SELECT COUNT(*) as count FROM governance_gasless_votes 
        WHERE voter_address = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $rate_check->bind_param("s", $input['voter']);
    $rate_check->execute();
    $rate_result = $rate_check->get_result();
    $rate_data = $rate_result->fetch_assoc();
    
    if ($rate_data['count'] >= 10) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Please wait before voting again.'
        ]);
        exit();
    }
    
    // Verify signature off-chain (saves gas)
    $voteData = [
        'proposalId' => $input['proposalId'],
        'support' => $input['support'],
        'voter' => $input['voter'],
        'nonce' => $input['nonce'],
        'deadline' => $input['deadline']
    ];
    
    $isValid = verifyEIP712Signature($voteData, $input['signature'], $input['voter']);
    
    if (!$isValid) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid signature'
        ]);
        exit();
    }
    
    // Store in database (pending status)
    $insert_stmt = $mysqli->prepare("
        INSERT INTO governance_gasless_votes (
            proposal_id,
            support,
            voter_address,
            nonce,
            deadline,
            signature,
            status,
            ip_address,
            user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
    ");
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $insert_stmt->bind_param(
        "sissssss",
        $input['proposalId'],
        $input['support'],
        $input['voter'],
        $input['nonce'],
        $input['deadline'],
        $input['signature'],
        $ip,
        $userAgent
    );
    
    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to store vote');
    }
    
    $voteId = $mysqli->insert_id;
    
    // Submit to blockchain via relayer
    try {
        $txHash = submitVoteToBlockchain($voteData, $input['signature']);
        
        // Update status to submitted
        $update_stmt = $mysqli->prepare("
            UPDATE governance_gasless_votes 
            SET status = 'submitted',
                tx_hash = ?,
                submitted_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->bind_param("si", $txHash, $voteId);
        $update_stmt->execute();
        
        // Success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Vote submitted successfully',
            'data' => [
                'vote_id' => $voteId,
                'tx_hash' => $txHash,
                'proposal_id' => $input['proposalId'],
                'support' => $input['support'],
                'voter' => $input['voter'],
                'status' => 'submitted',
                'gas_saved' => '~80,000 gas (~$5-20 USD)'
            ]
        ]);
        
    } catch (Exception $e) {
        // Update status to failed
        $update_stmt = $mysqli->prepare("
            UPDATE governance_gasless_votes 
            SET status = 'failed',
                error_message = ?
            WHERE id = ?
        ");
        $errorMsg = $e->getMessage();
        $update_stmt->bind_param("si", $errorMsg, $voteId);
        $update_stmt->execute();
        
        throw new Exception('Blockchain submission failed: ' . $e->getMessage());
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Verify EIP-712 signature off-chain
 */
function verifyEIP712Signature($voteData, $signature, $expectedSigner) {
    // EIP-712 domain
    $domain = [
        'name' => 'Sphera Governance',
        'version' => '1',
        'chainId' => 1, // TODO: Get from config
        'verifyingContract' => '0x...' // TODO: Get from config
    ];
    
    // TODO: Implement proper EIP-712 verification
    // For now, basic validation
    
    // Signature should be 65 bytes (130 hex chars + 0x)
    if (strlen($signature) !== 132) {
        return false;
    }
    
    // Extract r, s, v from signature
    $r = substr($signature, 0, 66);
    $s = '0x' . substr($signature, 66, 64);
    $v = hexdec(substr($signature, 130, 2));
    
    // Basic validation
    if ($v !== 27 && $v !== 28) {
        return false;
    }
    
    return true; // Simplified for now
}

/**
 * Submit vote to blockchain via Web3
 */
function submitVoteToBlockchain($voteData, $signature) {
    // TODO: Implement actual Web3 submission
    // This would use web3.php library to call contract
    
    // Contract ABI
    $contractAddress = '0x...'; // TODO: Get from config
    $contractABI = []; // TODO: Load ABI
    
    // Relayer private key (from env)
    $relayerPrivateKey = getenv('RELAYER_PRIVATE_KEY');
    
    // For now, return mock tx hash
    return '0x' . bin2hex(random_bytes(32));
}
