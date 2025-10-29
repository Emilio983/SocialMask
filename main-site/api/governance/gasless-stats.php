<?php
/**
 * ============================================
 * GASLESS VOTING STATISTICS
 * ============================================
 * Endpoint: GET /api/governance/gasless-stats.php
 * Returns statistics about gasless voting usage
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sphera';

try {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Get overall statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_votes,
            COUNT(DISTINCT voter_address) as unique_voters,
            COUNT(DISTINCT proposal_id) as proposals_voted,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(gas_saved) as total_gas_saved,
            AVG(gas_saved) as avg_gas_saved_per_vote
        FROM governance_gasless_votes
    ";
    
    $result = $mysqli->query($stats_query);
    $stats = $result->fetch_assoc();
    
    // Get relayer statistics
    $relayer_query = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(gas_used) as total_gas_used,
            AVG(gas_used) as avg_gas_used,
            SUM(CAST(gas_cost AS UNSIGNED)) as total_cost_wei
        FROM governance_relayer_transactions
    ";
    
    $relayer_result = $mysqli->query($relayer_query);
    $relayer_stats = $relayer_result->fetch_assoc();
    
    // Calculate savings
    $totalGasSaved = intval($stats['total_gas_saved']);
    $avgGasPrice = 50000000000; // 50 gwei estimate
    $ethPrice = 3000; // $3000 per ETH estimate
    
    $savedCostWei = $totalGasSaved * $avgGasPrice;
    $savedCostEth = $savedCostWei / 1e18;
    $savedCostUsd = $savedCostEth * $ethPrice;
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'stats' => [
            'votes' => [
                'total' => intval($stats['total_votes']),
                'unique_voters' => intval($stats['unique_voters']),
                'proposals_voted' => intval($stats['proposals_voted']),
                'pending' => intval($stats['pending']),
                'submitted' => intval($stats['submitted']),
                'confirmed' => intval($stats['confirmed']),
                'failed' => intval($stats['failed'])
            ],
            'gas_savings' => [
                'total_gas_saved' => $totalGasSaved,
                'avg_gas_saved_per_vote' => intval($stats['avg_gas_saved_per_vote']),
                'estimated_cost_saved_eth' => number_format($savedCostEth, 4),
                'estimated_cost_saved_usd' => number_format($savedCostUsd, 2)
            ],
            'relayer' => [
                'total_transactions' => intval($relayer_stats['total_transactions']),
                'total_gas_used' => intval($relayer_stats['total_gas_used']),
                'avg_gas_per_tx' => intval($relayer_stats['avg_gas_used']),
                'total_cost_wei' => $relayer_stats['total_cost_wei']
            ]
        ]
    ]);
    
    $mysqli->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
