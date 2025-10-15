<?php
/**
 * ============================================
 * DELEGATE VOTING POWER
 * ============================================
 * Endpoint: POST /api/governance/delegate.php
 * Allows users to delegate their voting power
 * 
 * Required fields:
 * - user_id: User ID from session
 * - wallet_address: Delegator's wallet address
 * - delegatee: Address to delegate to (can be self for self-delegation)
 * - signature: Cryptographic signature (optional for now)
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

require_once __DIR__ . '/helpers/governance-db.php';
require_once __DIR__ . '/helpers/governance-web3.php';
require_once __DIR__ . '/helpers/governance-utils.php';
require_once __DIR__ . '/../../api/check_session.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'You must be logged in to delegate'
    ]);
    exit();
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON input'
        ]);
        exit();
    }
    
    // Validate session user matches request user
    if ($_SESSION['user_id'] != ($input['user_id'] ?? 0)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden',
            'message' => 'User ID mismatch'
        ]);
        exit();
    }
    
    // Rate limiting
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!GovernanceUtils::checkRateLimit($clientIp, 'delegate', 3, 60)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Too many delegation requests. Please wait a minute.'
        ]);
        exit();
    }
    
    // Validate required fields
    $required = ['wallet_address', 'delegatee'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Missing required field: $field"
            ]);
            exit();
        }
    }
    
    // Validate delegation data
    $errors = GovernanceUtils::validateDelegationData($input);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Validation failed',
            'errors' => $errors
        ]);
        exit();
    }
    
    // Initialize helpers
    $db = new GovernanceDB();
    $web3 = new GovernanceWeb3();
    
    $walletAddress = $input['wallet_address'];
    $delegatee = $input['delegatee'];
    
    // Get token balance (must have tokens to delegate)
    $tokenBalance = $web3->getTokenBalance($walletAddress);
    
    if ($tokenBalance === '0' || empty($tokenBalance)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No tokens',
            'message' => 'You have no GOVSPHE tokens to delegate'
        ]);
        exit();
    }
    
    // Get current voting power
    $currentVotingPower = $web3->getVotingPower($walletAddress);
    
    // Check if already delegated to same address
    $existingDelegation = $db->getActiveDelegation($walletAddress);
    
    if ($existingDelegation && strtolower($existingDelegation['delegatee']) === strtolower($delegatee)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Already delegated',
            'message' => 'You have already delegated to this address',
            'current_delegation' => [
                'delegatee' => $existingDelegation['delegatee'],
                'delegated_at' => $existingDelegation['delegated_at']
            ]
        ]);
        exit();
    }
    
    // Verify signature if provided
    if (isset($input['signature']) && !empty($input['signature'])) {
        $message = "Delegate voting power to {$delegatee}";
        $isValid = $web3->verifySignature($message, $input['signature'], $walletAddress);
        
        if (!$isValid) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid signature',
                'message' => 'Signature verification failed'
            ]);
            exit();
        }
    }
    
    // Determine delegation type
    $isSelfDelegation = strtolower($walletAddress) === strtolower($delegatee);
    $delegationType = $isSelfDelegation ? 'self-delegation' : 'delegation';
    
    // Prepare delegation data
    $delegationData = [
        'user_id' => $_SESSION['user_id'],
        'wallet_address' => $walletAddress,
        'delegatee' => $delegatee,
        'voting_power' => $tokenBalance, // Token balance = max voting power to delegate
        'on_chain_tx' => $input['on_chain_tx'] ?? null
    ];
    
    // Save delegation to database (will revoke previous delegation automatically)
    $success = $db->saveDelegation($delegationData);
    
    if (!$success) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save delegation',
            'message' => 'Database error occurred while saving delegation'
        ]);
        exit();
    }
    
    // Get new voting power after delegation
    $newVotingPower = $isSelfDelegation ? $tokenBalance : '0';
    
    // Get delegatee info if not self-delegation
    $delegateeInfo = null;
    if (!$isSelfDelegation) {
        global $conn;
        $stmt = $conn->prepare("SELECT id, username, avatar_url FROM users WHERE wallet_address = ?");
        $stmt->execute([$delegatee]);
        $delegateeUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($delegateeUser) {
            $delegateeInfo = [
                'wallet' => $delegatee,
                'user_id' => $delegateeUser['id'],
                'username' => $delegateeUser['username'],
                'avatar_url' => $delegateeUser['avatar_url']
            ];
        } else {
            $delegateeInfo = [
                'wallet' => $delegatee,
                'user_id' => null,
                'username' => null,
                'avatar_url' => null
            ];
        }
    }
    
    // Log the action
    GovernanceUtils::logAction('delegate', $_SESSION['user_id'], [
        'delegatee' => $delegatee,
        'type' => $delegationType,
        'voting_power' => $tokenBalance
    ]);
    
    // Build response
    $response = [
        'success' => true,
        'message' => $isSelfDelegation 
            ? 'Successfully delegated to yourself. You can now vote!' 
            : "Successfully delegated your voting power to {$delegatee}",
        'data' => [
            'delegation' => [
                'type' => $delegationType,
                'delegator' => $walletAddress,
                'delegatee' => $delegatee,
                'delegatee_info' => $delegateeInfo,
                'voting_power_delegated' => [
                    'wei' => $tokenBalance,
                    'formatted' => GovernanceUtils::formatVotingPower($tokenBalance)
                ],
                'delegated_at' => date('Y-m-d\TH:i:s\Z'),
                'on_chain_tx' => $input['on_chain_tx'] ?? null
            ],
            'your_new_voting_power' => [
                'wei' => $newVotingPower,
                'formatted' => GovernanceUtils::formatVotingPower($newVotingPower)
            ],
            'capabilities' => [
                'can_vote' => bccomp($newVotingPower, '0') > 0,
                'can_propose' => bccomp($newVotingPower, '1000000000000000000000') >= 0, // >= 1000 GOV
                'can_change_delegation' => true
            ],
            'previous_delegation' => $existingDelegation ? [
                'delegatee' => $existingDelegation['delegatee'],
                'delegated_at' => $existingDelegation['delegated_at'],
                'revoked_at' => date('Y-m-d\TH:i:s\Z')
            ] : null
        ],
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Error in delegate.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'An unexpected error occurred while processing delegation',
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ]);
}
