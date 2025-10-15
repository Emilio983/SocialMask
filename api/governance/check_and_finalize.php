<?php
/**
 * Script para finalizar propuestas vencidas y verificar quórum
 * Se ejecuta automáticamente o mediante cron
 */
require_once __DIR__ . '/../../config/connection.php';

try {
    // Obtener propuestas activas que ya vencieron
    $stmt = $pdo->query("
        SELECT id, total_sphe_voted, quorum_required, total_votes_for, total_votes_against
        FROM governance_proposals
        WHERE status = 'active' AND voting_end <= NOW()
    ");
    $expiredProposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $finalized = 0;
    
    foreach ($expiredProposals as $proposal) {
        $spheVoted = floatval($proposal['total_sphe_voted']);
        $quorumRequired = floatval($proposal['quorum_required']);
        $votesFor = (int)$proposal['total_votes_for'];
        $votesAgainst = (int)$proposal['total_votes_against'];
        $totalVotes = $votesFor + $votesAgainst;
        
        $newStatus = 'rejected';
        
        // Verificar si se alcanzó el quórum
        if ($spheVoted >= $quorumRequired) {
            // Quórum alcanzado, verificar mayoría
            if ($totalVotes > 0 && $votesFor > $votesAgainst) {
                $newStatus = 'passed'; // Aprobada
            } else {
                $newStatus = 'rejected'; // Rechazada por mayoría en contra
            }
        } else {
            $newStatus = 'rejected'; // Rechazada por no alcanzar quórum
        }
        
        // Actualizar estado
        $updateStmt = $pdo->prepare("UPDATE governance_proposals SET status = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, $proposal['id']]);
        $finalized++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "$finalized propuestas finalizadas",
        'finalized' => $finalized
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('check_and_finalize error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al finalizar propuestas'
    ], JSON_UNESCAPED_UNICODE);
}
