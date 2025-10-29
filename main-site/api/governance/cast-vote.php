<?php
/**
 * ============================================
 * CAST VOTE ON PROPOSAL
 * ============================================
 * Endpoint: POST /api/governance/cast-vote.php
 * Allows users to vote on governance proposals
 * 
 * Required fields:
 * - user_id: User ID from session
 * - wallet_address: Voter's wallet address
 * - proposal_id: ID of the proposal
 * - vote_type: 0=AGAINST, 1=FOR, 2=ABSTAIN
 * - reason: Optional reason for vote
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
        'message' => 'You must be logged in to vote'
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
    if (!GovernanceUtils::checkRateLimit($clientIp, 'cast_vote', 5, 60)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Too many vote requests. Please wait a minute.'
        ]);
        exit();
    }
    
    // Validate required fields
    $required = ['proposal_id', 'wallet_address', 'vote_type'];
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
    
    // Validate vote data
    $errors = GovernanceUtils::validateVoteData($input);
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
    
    $proposalId = $input['proposal_id'];
    $walletAddress = $input['wallet_address'];
    $voteType = (int) $input['vote_type'];
    $reason = $input['reason'] ?? null;
    
    // Get proposal details
    $proposal = $db->getProposalDetail($proposalId);
    
    if (!$proposal) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Proposal not found'
        ]);
        exit();
    }
    
    // Check if proposal is in active state
    if ($proposal['status'] !== 'active') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Proposal not active',
            'message' => "Proposal is currently '{$proposal['status']}'. Only active proposals can receive votes.",
            'proposal_status' => $proposal['status']
        ]);
        exit();
    }
    
    // Check if voting period has ended
    if ($proposal['voting_ends_at']) {
        $now = new DateTime();
        $endTime = new DateTime($proposal['voting_ends_at']);
        
        if ($now > $endTime) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Voting period ended',
                'message' => 'The voting period for this proposal has ended'
            ]);
            exit();
        }
    }
    
    // Check if user has already voted
    if ($db->hasUserVoted($proposalId, $walletAddress)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Already voted',
            'message' => 'You have already voted on this proposal. Votes cannot be changed.'
        ]);
        exit();
    }
    
    // Get voting power from blockchain
    $votingPower = $web3->getVotingPower($walletAddress);
    
    if ($votingPower === '0' || empty($votingPower)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No voting power',
            'message' => 'You have no voting power. Please acquire GOVSPHE tokens and delegate to yourself.'
        ]);
        exit();
    }
    
    // Verify signature if provided
    if (isset($input['signature']) && !empty($input['signature'])) {
        $message = "Vote on proposal {$proposalId} with type {$voteType}";
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
    
    // Prepare vote data
    $voteData = [
        'proposal_id' => $proposalId,
        'user_id' => $_SESSION['user_id'],
        'wallet_address' => $walletAddress,
        'vote_type' => $voteType,
        'voting_power' => $votingPower,
        'reason' => $reason,
        'on_chain_tx' => $input['on_chain_tx'] ?? null
    ];
    
    // Save vote to database
    $success = $db->saveVote($voteData);
    
    if (!$success) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save vote',
            'message' => 'Database error occurred while saving your vote'
        ]);
        exit();
    }
    
    // Get updated vote counts
    $updatedProposal = $db->getProposalDetail($proposalId);
    $progress = GovernanceUtils::getProposalProgress($updatedProposal);
    
    // Log the action
    GovernanceUtils::logAction('cast_vote', $_SESSION['user_id'], [
        'proposal_id' => $proposalId,
        'vote_type' => $voteType,
        'voting_power' => $votingPower
    ]);
    
    // Build response
    $voteTypeName = ['against', 'for', 'abstain'][$voteType];
    
    $response = [
        'success' => true,
        'message' => 'Vote cast successfully',
        'data' => [
            'vote' => [
                'proposal_id' => $proposalId,
                'vote_type' => $voteType,
                'vote_type_name' => $voteTypeName,
                'voting_power' => [
                    'wei' => $votingPower,
                    'formatted' => GovernanceUtils::formatVotingPower($votingPower)
                ],
                'reason' => $reason,
                'voted_at' => date('Y-m-d\TH:i:s\Z')
            ],
            'proposal_stats' => [
                'votes_for' => [
                    'wei' => $updatedProposal['votes_for'],
                    'formatted' => GovernanceUtils::formatVotingPower($updatedProposal['votes_for']),
                    'percentage' => $progress['for_percentage']
                ],
                'votes_against' => [
                    'wei' => $updatedProposal['votes_against'],
                    'formatted' => GovernanceUtils::formatVotingPower($updatedProposal['votes_against']),
                    'percentage' => $progress['against_percentage']
                ],
                'votes_abstain' => [
                    'wei' => $updatedProposal['votes_abstain'],
                    'formatted' => GovernanceUtils::formatVotingPower($updatedProposal['votes_abstain']),
                    'percentage' => $progress['abstain_percentage']
                ],
                'total_votes' => [
                    'wei' => $progress['total_votes'],
                    'formatted' => GovernanceUtils::formatVotingPower($progress['total_votes'])
                ],
                'is_passing' => $progress['is_passing'],
                'quorum_reached' => $progress['quorum_reached']
            ]
        ],
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Error in cast-vote.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'An unexpected error occurred while processing your vote',
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ]);
}
