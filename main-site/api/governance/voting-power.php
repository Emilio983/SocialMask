<?php
/**
 * ============================================
 * GET VOTING POWER
 * ============================================
 * Endpoint: GET /api/governance/voting-power.php?wallet=0x...
 * Returns voting power and delegation info for a wallet
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
require_once __DIR__ . '/../../config/connection.php';

try {
    // Get wallet address from query params
    $wallet = $_GET['wallet'] ?? '';
    
    if (empty($wallet)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Wallet address is required',
            'message' => 'Please provide wallet parameter'
        ]);
        exit();
    }
    
    // Validate wallet address
    if (!GovernanceUtils::isValidWalletAddress($wallet)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid wallet address',
            'message' => 'Wallet address must be a valid Ethereum address'
        ]);
        exit();
    }
    
    // Initialize helpers
    $db = new GovernanceDB();
    $web3 = new GovernanceWeb3();
    
    // Get user info from database
    $stmt = $conn->prepare("SELECT id, username, avatar_url FROM users WHERE wallet_address = ?");
    $stmt->execute([$wallet]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $userId = $user['id'] ?? null;
    $username = $user['username'] ?? null;
    
    // Get voting power from blockchain
    $votingPowerWei = $web3->getVotingPower($wallet);
    $tokenBalanceWei = $web3->getTokenBalance($wallet);
    $delegateAddress = $web3->getDelegate($wallet);
    
    // Get delegation info from database
    $activeDelegation = $db->getActiveDelegation($wallet);
    
    // Determine delegation status
    $delegationStatus = 'not_delegated';
    $delegatedTo = null;
    
    if ($delegateAddress !== '0x0000000000000000000000000000000000000000') {
        if (strtolower($delegateAddress) === strtolower($wallet)) {
            $delegationStatus = 'self_delegated';
        } else {
            $delegationStatus = 'delegated_to_other';
            $delegatedTo = $delegateAddress;
        }
    }
    
    // Get delegations from others (who delegated to this wallet)
    $stmt = $conn->prepare("
        SELECT 
            d.wallet_address,
            d.voting_power,
            u.username,
            u.avatar_url
        FROM governance_delegations d
        LEFT JOIN users u ON d.wallet_address = u.wallet_address
        WHERE d.delegatee = ? AND d.revoked_at IS NULL
        ORDER BY d.voting_power DESC
        LIMIT 10
    ");
    $stmt->execute([$wallet]);
    $delegatedFrom = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get governance activity
    $activity = [];
    if ($userId) {
        $activity = $db->getUserActivity($userId);
    }
    
    // Check if can propose (needs >= 1000 GOVSPHE voting power)
    $proposalThreshold = '1000000000000000000000'; // 1000 * 10^18
    $canPropose = bccomp($votingPowerWei, $proposalThreshold) >= 0;
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'wallet' => $wallet,
            'user_id' => $userId,
            'username' => $username,
            'avatar_url' => $user['avatar_url'] ?? null,
            'voting_power' => [
                'wei' => $votingPowerWei,
                'ether' => GovernanceWeb3::weiToEther($votingPowerWei),
                'formatted' => GovernanceUtils::formatVotingPower($votingPowerWei)
            ],
            'token_balance' => [
                'wei' => $tokenBalanceWei,
                'ether' => GovernanceWeb3::weiToEther($tokenBalanceWei),
                'formatted' => GovernanceUtils::formatVotingPower($tokenBalanceWei)
            ],
            'delegation' => [
                'status' => $delegationStatus,
                'delegated_to' => $delegatedTo,
                'delegated_from' => array_map(function($d) {
                    return [
                        'wallet' => $d['wallet_address'],
                        'username' => $d['username'],
                        'avatar_url' => $d['avatar_url'],
                        'voting_power' => [
                            'wei' => $d['voting_power'],
                            'ether' => GovernanceWeb3::weiToEther($d['voting_power']),
                            'formatted' => GovernanceUtils::formatVotingPower($d['voting_power'])
                        ]
                    ];
                }, $delegatedFrom),
                'total_delegated_from' => count($delegatedFrom)
            ],
            'capabilities' => [
                'can_propose' => $canPropose,
                'proposal_threshold' => [
                    'wei' => $proposalThreshold,
                    'ether' => '1000',
                    'formatted' => '1,000 GOVSPHE'
                ],
                'can_vote' => bccomp($votingPowerWei, '0') > 0,
                'can_delegate' => bccomp($tokenBalanceWei, '0') > 0
            ],
            'activity' => [
                'proposals_created' => (int) ($activity['proposals_created'] ?? 0),
                'votes_cast' => (int) ($activity['votes_cast'] ?? 0),
                'delegations_made' => (int) ($activity['delegations_made'] ?? 0),
                'last_proposal_at' => $activity['last_proposal_at'] ?? null,
                'last_vote_at' => $activity['last_vote_at'] ?? null,
                'last_delegation_at' => $activity['last_delegation_at'] ?? null
            ]
        ],
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Error in voting-power.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'Failed to retrieve voting power',
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ]);
}
