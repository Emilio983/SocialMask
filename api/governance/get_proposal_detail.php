<?php
/**
 * API: Obtener detalle de una propuesta
 * GET /api/governance/get_proposal_detail.php?id=xxx
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/connection.php';

try {
    $proposalId = $_GET['id'] ?? '';
    
    if (empty($proposalId)) {
        throw new Exception('Proposal ID requerido');
    }
    
    // Get proposal
    $stmt = $pdo->prepare('
        SELECT proposal_id, proposer_address, title, description, category, status,
               for_votes, against_votes, abstain_votes,
               start_block, end_block, created_at, updated_at
        FROM governance_proposals
        WHERE proposal_id = ?
    ');
    $stmt->execute([$proposalId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposal) {
        throw new Exception('Propuesta no encontrada');
    }
    
    // Calculate percentages
    $totalVotes = $proposal['for_votes'] + $proposal['against_votes'] + $proposal['abstain_votes'];
    $proposal['for_percentage'] = $totalVotes > 0 ? round(($proposal['for_votes'] / $totalVotes) * 100, 2) : 0;
    $proposal['against_percentage'] = $totalVotes > 0 ? round(($proposal['against_votes'] / $totalVotes) * 100, 2) : 0;
    $proposal['abstain_percentage'] = $totalVotes > 0 ? round(($proposal['abstain_votes'] / $totalVotes) * 100, 2) : 0;
    $proposal['total_votes'] = $totalVotes;
    
    // Get votes for this proposal
    $stmt = $pdo->prepare('
        SELECT voter_address, support, voting_power, reason, voted_at
        FROM governance_votes
        WHERE proposal_id = ?
        ORDER BY voted_at DESC
        LIMIT 50
    ');
    $stmt->execute([$proposalId]);
    $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'proposal' => $proposal,
        'votes' => $votes
    ]);
    
} catch (Exception $e) {
    error_log('get_proposal_detail error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
