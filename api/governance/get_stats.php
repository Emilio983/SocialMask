<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/connection.php';

try {
    // Total proposals
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM governance_proposals');
    $totalProposals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active proposals
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM governance_proposals WHERE status = 'active'");
    $activeProposals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Passed proposals
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM governance_proposals WHERE status = 'passed'");
    $passedProposals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total votes cast
    $stmt = $pdo->query('SELECT SUM(total_votes_for + total_votes_against) as total FROM governance_proposals');
    $totalVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_proposals' => (int)$totalProposals,
            'active_proposals' => (int)$activeProposals,
            'executed_proposals' => (int)$passedProposals,
            'total_voters' => 0, // Would need votes table
            'total_votes' => (int)$totalVotes,
            'participation_rate' => 0
        ]
    ]);
    
} catch (Exception $e) {
    error_log('get_stats error: ' . $e->getMessage());
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_proposals' => 0,
            'active_proposals' => 0,
            'executed_proposals' => 0,
            'total_voters' => 0,
            'total_votes' => 0,
            'participation_rate' => 0
        ]
    ]);
}
