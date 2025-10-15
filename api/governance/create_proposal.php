<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';

requireAuth();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $category = trim($input['category'] ?? '');
    
    if (empty($title) || strlen($title) < 10) {
        throw new Exception('El título debe tener al menos 10 caracteres');
    }
    
    if (empty($description) || strlen($description) < 50) {
        throw new Exception('La descripción debe tener al menos 50 caracteres');
    }
    
    $validCategories = ['community_rule', 'fee_change', 'feature_request', 'platform_change'];
    if (!in_array($category, $validCategories)) {
        throw new Exception('Categoría inválida');
    }
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare('SELECT smart_account_address FROM smart_accounts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        throw new Exception('Necesitas una Smart Account para crear propuestas');
    }
    
    $stmt = $pdo->prepare('SELECT balance_sphe FROM wallet_balances WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $spheBalance = floatval($balance['balance_sphe'] ?? 0);
    
    if ($spheBalance < 100) {
        throw new Exception('Necesitas al menos 100 SPHE para crear una propuesta. Actualmente tienes ' . number_format($spheBalance, 2) . ' SPHE');
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM governance_proposals WHERE status = 'active'");
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($activeCount >= 15) {
        throw new Exception('Hay 15 propuestas activas (máximo permitido). Espera a que algunas se completen.');
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM governance_proposals WHERE creator_id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $userActiveCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($userActiveCount >= 2) {
        throw new Exception('Ya tienes 2 propuestas activas. Espera a que se completen para crear más.');
    }
    
    $votingStart = date('Y-m-d H:i:s');
    $votingEnd = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    // Quórum: Mínimo 1000 SPHE deben votar
    $quorumRequired = 1000;
    
    $stmt = $pdo->prepare('
        INSERT INTO governance_proposals 
        (creator_id, title, description, proposal_type, voting_start, voting_end, 
         min_sphe_to_vote, quorum_required, status)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?, "active")
    ');
    $stmt->execute([
        $userId,
        $title,
        $description,
        $category,
        $votingStart,
        $votingEnd,
        $quorumRequired
    ]);
    
    $proposalId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => '¡Propuesta creada exitosamente! Votación activa por 3 días. Se necesitan mínimo 1,000 SPHE votando para alcanzar el quórum.',
        'proposal_id' => $proposalId,
        'voting_end' => $votingEnd,
        'quorum_required' => $quorumRequired
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('create_proposal error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
