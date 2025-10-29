<?php
/**
 * ============================================
 * GET PROPOSAL DETAIL
 * ============================================
 * Endpoint: GET /api/governance/get-proposal.php?id=123...
 * Returns full details of a specific proposal
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET.'
    ]);
    exit();
}

require_once __DIR__ . '/helpers/governance-db.php';
require_once __DIR__ . '/helpers/governance-web3.php';
require_once __DIR__ . '/helpers/governance-utils.php';

try {
    // Get proposal ID from query params
    $proposalId = $_GET['id'] ?? '';
    
    if (empty($proposalId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Proposal ID is required',
            'message' => 'Please provide id parameter'
        ]);
        exit();
    }
    
    // Validate proposal ID
    if (!GovernanceUtils::isValidProposalId($proposalId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid proposal ID',
            'message' => 'Proposal ID format is invalid'
        ]);
        exit();
    }
    
    // Initialize helpers
    $db = new GovernanceDB();
    $web3 = new GovernanceWeb3();
    
    // Get proposal from database
    $proposal = $db->getProposalDetail($proposalId);
    
    if (!$proposal) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Proposal not found',
            'message' => 'No proposal exists with this ID'
        ]);
        exit();
    }
    
    // Get current state from blockchain (for accuracy)
    try {
        $onChainState = $web3->getProposalState($proposalId);
        $onChainStateName = GovernanceUtils::getStateName($onChainState);
        
        // Update database if state changed
        if ($onChainStateName !== $proposal['status'] && $onChainState >= 0) {
            $db->updateProposal($proposalId, [
                'status' => $onChainStateName,
                'state_number' => $onChainState
            ]);
            $proposal['status'] = $onChainStateName;
            $proposal['state_number'] = $onChainState;
        }
        
        // Get current votes from blockchain
        $onChainVotes = $web3->getProposalVotes($proposalId);
        if (!empty($onChainVotes)) {
            $proposal['votes_for'] = $onChainVotes['for'];
            $proposal['votes_against'] = $onChainVotes['against'];
            $proposal['votes_abstain'] = $onChainVotes['abstain'];
        }
    } catch (Exception $e) {
        // Continue with database values if blockchain call fails
        error_log("Failed to get on-chain data: " . $e->getMessage());
    }
    
    // Calculate progress
    $progress = GovernanceUtils::getProposalProgress($proposal);
    
    // Decode calldatas
    $actions = [];
    for ($i = 0; $i < count($proposal['targets']); $i++) {
        $decoded = GovernanceUtils::decodeCalldata($proposal['calldatas'][$i]);
        
        $actions[] = [
            'target' => $proposal['targets'][$i],
            'value' => $proposal['values'][$i],
            'calldata' => $proposal['calldatas'][$i],
            'decoded' => $decoded
        ];
    }
    
    // Format votes breakdown
    $votesBreakdown = array_map(function($vote) {
        return [
            'voter' => $vote['wallet_address'],
            'user_id' => $vote['user_id'],
            'username' => $vote['username'],
            'avatar_url' => $vote['avatar_url'],
            'vote_type' => $vote['vote_type_name'],
            'voting_power' => [
                'wei' => $vote['voting_power'],
                'formatted' => GovernanceUtils::formatVotingPower($vote['voting_power'])
            ],
            'reason' => $vote['reason'],
            'timestamp' => $vote['voted_at'],
            'tx_hash' => $vote['on_chain_tx']
        ];
    }, $proposal['votes_breakdown']);
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'proposal' => [
                'proposal_id' => $proposal['proposal_id'],
                'category' => (int) $proposal['category'],
                'category_name' => GovernanceUtils::getCategoryName($proposal['category']),
                'title' => $proposal['title'],
                'description' => $proposal['description'],
                'proposer' => [
                    'wallet' => $proposal['proposer_wallet'],
                    'user_id' => $proposal['proposer_user_id'],
                    'username' => $proposal['proposer_username'],
                    'avatar_url' => $proposal['proposer_avatar']
                ],
                'status' => $proposal['status'],
                'state_number' => (int) $proposal['state_number'],
                'votes' => [
                    'for' => [
                        'wei' => $proposal['votes_for'],
                        'formatted' => GovernanceUtils::formatVotingPower($proposal['votes_for']),
                        'percentage' => $progress['for_percentage']
                    ],
                    'against' => [
                        'wei' => $proposal['votes_against'],
                        'formatted' => GovernanceUtils::formatVotingPower($proposal['votes_against']),
                        'percentage' => $progress['against_percentage']
                    ],
                    'abstain' => [
                        'wei' => $proposal['votes_abstain'],
                        'formatted' => GovernanceUtils::formatVotingPower($proposal['votes_abstain']),
                        'percentage' => $progress['abstain_percentage']
                    ],
                    'total' => [
                        'wei' => $progress['total_votes'],
                        'formatted' => GovernanceUtils::formatVotingPower($progress['total_votes'])
                    ]
                ],
                'quorum' => [
                    'required' => [
                        'wei' => '440000000000000000000', // 4% of 11000 GOV
                        'formatted' => '440 GOVSPHE'
                    ],
                    'reached' => $progress['quorum_reached'],
                    'percentage' => 4
                ],
                'progress' => [
                    'is_passing' => $progress['is_passing'],
                    'quorum_reached' => $progress['quorum_reached']
                ],
                'timeline' => [
                    'created_at' => $proposal['created_at'],
                    'voting_starts_at' => $proposal['voting_starts_at'],
                    'voting_ends_at' => $proposal['voting_ends_at'],
                    'queued_at' => $proposal['queued_at'],
                    'executed_at' => $proposal['executed_at'],
                    'execution_eta' => $proposal['queued_at'] 
                        ? date('Y-m-d\TH:i:s\Z', strtotime($proposal['queued_at']) + 172800) // +2 days
                        : null
                ],
                'time_remaining' => $proposal['voting_ends_at']
                    ? GovernanceUtils::getTimeRemaining($proposal['voting_ends_at'])
                    : null,
                'actions' => $actions,
                'votes_breakdown' => $votesBreakdown,
                'on_chain_tx' => $proposal['on_chain_tx']
            ]
        ],
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Error in get-proposal.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'Failed to retrieve proposal details',
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ]);
}
