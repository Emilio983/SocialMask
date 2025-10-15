<?php
/**
 * ============================================
 * GET PROPOSALS LIST
 * ============================================
 * Endpoint: GET /api/governance/get-proposals.php
 * Returns list of governance proposals with filters
 * 
 * Query Parameters:
 * - category: 0-4 (optional)
 * - status: pending|active|succeeded|defeated|queued|executed (optional)
 * - page: page number (default: 1)
 * - limit: items per page (default: 20)
 * - search: search term (optional)
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
require_once __DIR__ . '/helpers/governance-utils.php';

try {
    // Get query parameters
    $filters = [];
    
    if (isset($_GET['category']) && $_GET['category'] !== '') {
        $category = (int) $_GET['category'];
        if (GovernanceUtils::isValidCategory($category)) {
            $filters['category'] = $category;
        }
    }
    
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $status = $_GET['status'];
        if (GovernanceUtils::isValidStatus($status)) {
            $filters['status'] = $status;
        }
    }
    
    if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
        $filters['user_id'] = (int) $_GET['user_id'];
    }
    
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $filters['search'] = trim($_GET['search']);
    }
    
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 20;
    
    // Initialize database helper
    $db = new GovernanceDB();
    
    // Get proposals
    $result = $db->getProposals($filters, $page, $limit);
    
    // Format proposals for response
    $proposals = array_map(function($proposal) {
        $progress = GovernanceUtils::getProposalProgress($proposal);
        
        return [
            'proposal_id' => $proposal['proposal_id'],
            'category' => (int) $proposal['category'],
            'category_name' => GovernanceUtils::getCategoryName($proposal['category']),
            'title' => $proposal['title'],
            'description_preview' => substr(strip_tags($proposal['description']), 0, 200) . '...',
            'proposer' => [
                'wallet' => $proposal['wallet_address'],
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
            'progress' => [
                'is_passing' => $progress['is_passing'],
                'quorum_reached' => $progress['quorum_reached']
            ],
            'vote_count' => (int) $proposal['vote_count'],
            'timeline' => [
                'created_at' => $proposal['created_at'],
                'voting_starts_at' => $proposal['voting_starts_at'],
                'voting_ends_at' => $proposal['voting_ends_at'],
                'queued_at' => $proposal['queued_at'],
                'executed_at' => $proposal['executed_at']
            ],
            'time_remaining' => $proposal['voting_ends_at'] 
                ? GovernanceUtils::getTimeRemaining($proposal['voting_ends_at'])
                : null
        ];
    }, $result['proposals']);
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'proposals' => $proposals,
            'pagination' => $result['pagination'],
            'filters_applied' => $filters
        ],
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Error in get-proposals.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'Failed to retrieve proposals',
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ]);
}
