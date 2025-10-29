<?php
/**
 * ============================================
 * GET GOVERNANCE STATISTICS
 * ============================================
 * Endpoint: GET /api/governance/stats.php
 * Returns general governance statistics
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
    // Initialize database helper
    $db = new GovernanceDB();
    
    // Get governance stats
    $stats = $db->getGovernanceStats();
    
    if (empty($stats)) {
        // No stats yet, return empty structure
        $stats = [
            'stat_date' => date('Y-m-d'),
            'total_proposals' => 0,
            'active_proposals' => 0,
            'total_voters' => 0,
            'total_votes_cast' => 0,
            'total_gov_tokens' => '0',
            'average_participation' => 0,
            'proposals_by_category' => [
                'parameter_change' => 0,
                'treasury_management' => 0,
                'contract_upgrade' => 0,
                'feature_proposal' => 0,
                'emergency_action' => 0
            ],
            'proposals_by_status' => [
                'pending' => 0,
                'active' => 0,
                'succeeded' => 0,
                'defeated' => 0,
                'queued' => 0,
                'executed' => 0,
                'cancelled' => 0
            ]
        ];
    }
    
    // Format numbers for display
    $response = [
        'success' => true,
        'stats' => [
            'total_proposals' => (int) $stats['total_proposals'],
            'active_proposals' => (int) $stats['active_proposals'],
            'total_voters' => (int) $stats['total_voters'],
            'total_votes_cast' => (int) $stats['total_votes_cast'],
            'total_gov_tokens' => $stats['total_gov_tokens'] ?? '0',
            'total_gov_tokens_formatted' => GovernanceUtils::formatVotingPower($stats['total_gov_tokens'] ?? '0'),
            'average_participation' => number_format((float) $stats['average_participation'], 2) . '%',
            'proposals_by_category' => $stats['proposals_by_category'] ?? [
                'parameter_change' => 0,
                'treasury_management' => 0,
                'contract_upgrade' => 0,
                'feature_proposal' => 0,
                'emergency_action' => 0
            ],
            'proposals_by_status' => $stats['proposals_by_status'] ?? [
                'pending' => 0,
                'active' => 0,
                'succeeded' => 0,
                'defeated' => 0,
                'queued' => 0,
                'executed' => 0,
                'cancelled' => 0
            ],
            'last_updated' => $stats['created_at'] ?? date('Y-m-d H:i:s')
        ],
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Error in stats.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'Failed to retrieve governance statistics',
        'timestamp' => date('Y-m-d\TH:i:s\Z')
    ]);
}
