<?php
/**
 * ============================================
 * CREATE GOVERNANCE PROPOSAL
 * ============================================
 * Endpoint: POST /api/governance/create-proposal.php
 * Allows users with >= 1000 GOVSPHE to create proposals
 * 
 * Required fields:
 * - user_id: User ID from session
 * - wallet_address: Creator's wallet
 * - title: Proposal title (max 255 chars)
 * - description: Detailed description (markdown, max 10,000 chars)
 * - category: One of: parameter_change, treasury_allocation, protocol_upgrade, ecosystem_initiative, emergency_action
 * - targets: Array of contract addresses to call
 * - values: Array of ETH values for each call
 * - calldatas: Array of encoded function calls
 * - signature: Cryptographic signature (optional)
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
        'message' => 'You must be logged in to create a proposal'
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
    
    // Rate limiting: 2 proposals per hour
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!GovernanceUtils::checkRateLimit($clientIp, 'create_proposal', 2, 3600)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Too many proposals. You can create 2 proposals per hour.'
        ]);
        exit();
    }
    
    // Validate required fields
    $required = ['wallet_address', 'title', 'description', 'category', 'targets', 'values', 'calldatas'];
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
    
    // Validate proposal data
    $errors = GovernanceUtils::validateProposalData($input);
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
    
    // Check voting power (must have >= 1000 GOVSPHE)
    $votingPower = $web3->getVotingPower($walletAddress);
    $minimumPower = '1000000000000000000000'; // 1000 GOVSPHE in wei
    
    if (bccomp($votingPower, $minimumPower) < 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient voting power',
            'message' => 'You need at least 1,000 GOVSPHE tokens to create a proposal',
            'your_voting_power' => [
                'wei' => $votingPower,
                'formatted' => GovernanceUtils::formatVotingPower($votingPower)
            ],
            'minimum_required' => [
                'wei' => $minimumPower,
                'formatted' => '1,000 GOVSPHE'
            ]
        ]);
        exit();
    }
    
    // Sanitize and validate inputs
    $title = substr(trim($input['title']), 0, 255);
    $description = substr(trim($input['description']), 0, 10000);
    $category = $input['category'];
    $targets = $input['targets'];
    $values = $input['values'];
    $calldatas = $input['calldatas'];
    
    // Validate arrays have same length
    if (count($targets) !== count($values) || count($targets) !== count($calldatas)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Array length mismatch',
            'message' => 'targets, values, and calldatas must have the same length'
        ]);
        exit();
    }
    
    // Validate at least one action
    if (count($targets) === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No actions',
            'message' => 'Proposal must have at least one action'
        ]);
        exit();
    }
    
    // Validate max 10 actions
    if (count($targets) > 10) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Too many actions',
            'message' => 'Proposal can have maximum 10 actions'
        ]);
        exit();
    }
    
    // Validate all targets are valid addresses
    foreach ($targets as $target) {
        if (!GovernanceUtils::isValidEthereumAddress($target)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid target address',
                'message' => "Invalid Ethereum address: {$target}"
            ]);
            exit();
        }
    }
    
    // Verify signature if provided
    if (isset($input['signature']) && !empty($input['signature'])) {
        $message = "Create proposal: {$title}";
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
    
    // Generate proposal ID (hash of all parameters)
    $proposalId = GovernanceUtils::generateProposalId([
        'title' => $title,
        'description' => $description,
        'targets' => $targets,
        'values' => $values,
        'calldatas' => $calldatas,
        'proposer' => $walletAddress,
        'timestamp' => time()
    ]);
    
    // Calculate timeline (based on smart contract parameters)
    $now = time();
    $votingDelay = 86400; // 1 day
    $votingPeriod = 604800; // 7 days
    
    $createdAt = date('Y-m-d H:i:s', $now);
    $votingStartsAt = date('Y-m-d H:i:s', $now + $votingDelay);
    $votingEndsAt = date('Y-m-d H:i:s', $now + $votingDelay + $votingPeriod);
    
    // Prepare proposal data
    $proposalData = [
        'proposal_id' => $proposalId,
        'proposer_user_id' => $_SESSION['user_id'],
        'proposer_wallet' => $walletAddress,
        'title' => $title,
        'description' => $description,
        'category' => $category,
        'status' => 'pending', // Will change to 'active' after voting delay
        'targets' => json_encode($targets),
        'values' => json_encode($values),
        'calldatas' => json_encode($calldatas),
        'created_at' => $createdAt,
        'voting_starts_at' => $votingStartsAt,
        'voting_ends_at' => $votingEndsAt,
        'on_chain_tx' => $input['on_chain_tx'] ?? null
    ];
    
    // Save proposal to database
    $success = $db->saveProposal($proposalData);
    
    if (!$success) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save proposal',
            'message' => 'Database error occurred while saving proposal'
        ]);
        exit();
    }
    
    // Decode calldatas for display
    $decodedActions = [];
    for ($i = 0; $i < count($targets); $i++) {
        $decodedActions[] = [
            'target' => $targets[$i],
            'value' => $values[$i],
            'calldata' => $calldatas[$i],
            'decoded' => GovernanceUtils::decodeCalldata($calldatas[$i])
        ];
    }
    
    // Log the action
    GovernanceUtils::logAction('create_proposal', $_SESSION['user_id'], [
        'proposal_id' => $proposalId,
        'title' => $title,
        'category' => $category
    ]);
    
    // Build response
    $response = [
        'success' => true,
        'message' => 'Proposal created successfully',
        'data' => [
            'proposal' => [
                'id' => $proposalId,
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'status' => 'pending',
                'proposer' => [
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'] ?? null,
                    'wallet' => $walletAddress,
                    'voting_power' => [
                        'wei' => $votingPower,
                        'formatted' => GovernanceUtils::formatVotingPower($votingPower)
                    ]
                ],
                'actions' => $decodedActions,
                'timeline' => [
                    'created_at' => $createdAt,
                    'voting_starts_at' => $votingStartsAt,
                    'voting_ends_at' => $votingEndsAt,
                    'voting_delay_hours' => 24,
                    'voting_period_days' => 7
                ],
                'votes' => [
                    'for' => ['wei' => '0', 'formatted' => '0'],
                    'against' => ['wei' => '0', 'formatted' => '0'],
                    'abstain' => ['wei' => '0', 'formatted' => '0']
                ],
                'on_chain_tx' => $input['on_chain_tx'] ?? null
            ],
            'next_steps' => [
                'description' => 'Your proposal is now in pending state',
                'voting_opens_in' => GovernanceUtils::getTimeRemaining(strtotime($votingStartsAt)),
                'actions_required' => [
                    'Share your proposal with the community',
                    'Engage in discussion on forums',
                    'Prepare to answer questions when voting opens'
                ]
            ]
        ],
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ];
    
    http_response_code(201);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Error in create-proposal.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'An unexpected error occurred while creating proposal',
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ]);
}
