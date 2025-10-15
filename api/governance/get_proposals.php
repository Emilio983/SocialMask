<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/connection.php';

try {
    $status = $_GET['status'] ?? '';
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;
    
    $where = ['1=1'];
    $params = [];
    
    if ($status) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    
    if ($category) {
        $where[] = 'proposal_type = ?';
        $params[] = $category;
    }
    
    if ($search) {
        $where[] = '(title LIKE ? OR description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM governance_proposals WHERE $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT id as proposal_id, title, description, proposal_type as category, status,
               total_votes_for as for_votes, total_votes_against as against_votes,
               total_sphe_voted, quorum_required, 0 as abstain_votes,
               voting_start, voting_end, created_at
        FROM governance_proposals
        WHERE $whereClause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$perPage, $offset]));
    $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($proposals as &$proposal) {
        $totalVotes = $proposal['for_votes'] + $proposal['against_votes'];
        $spheVoted = floatval($proposal['total_sphe_voted']);
        $quorumRequired = floatval($proposal['quorum_required']);
        
        $proposal['for_percentage'] = $totalVotes > 0 ? round(($proposal['for_votes'] / $totalVotes) * 100, 2) : 0;
        $proposal['against_percentage'] = $totalVotes > 0 ? round(($proposal['against_votes'] / $totalVotes) * 100, 2) : 0;
        $proposal['abstain_percentage'] = 0;
        $proposal['total_votes'] = $totalVotes;
        $proposal['quorum_reached'] = $spheVoted >= $quorumRequired;
        $proposal['quorum_percentage'] = $quorumRequired > 0 ? round(($spheVoted / $quorumRequired) * 100, 2) : 0;
        
        // Traducir estados a español
        $statusTranslations = [
            'draft' => 'Borrador',
            'active' => 'Activa',
            'passed' => 'Aprobada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada'
        ];
        $proposal['status_text'] = $statusTranslations[$proposal['status']] ?? $proposal['status'];
        
        // Traducir categorías a español
        $categoryTranslations = [
            'community_rule' => 'Regla de Comunidad',
            'fee_change' => 'Cambio de Tarifa',
            'feature_request' => 'Solicitud de Función',
            'platform_change' => 'Cambio de Plataforma'
        ];
        $proposal['category_text'] = $categoryTranslations[$proposal['category']] ?? $proposal['category'];
    }
    
    echo json_encode([
        'success' => true,
        'proposals' => $proposals,
        'pagination' => [
            'total' => (int)$total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => ceil($total / $perPage)
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('get_proposals error: ' . $e->getMessage());
    echo json_encode([
        'success' => true,
        'proposals' => [],
        'pagination' => [
            'total' => 0,
            'per_page' => 10,
            'current_page' => 1,
            'total_pages' => 0
        ]
    ], JSON_UNESCAPED_UNICODE);
}
