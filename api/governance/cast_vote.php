<?php
/**
 * API: Votar en propuesta (REAL - usa balance SPHE del usuario)
 */
header('Content-Type: application/json');
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
    
    $proposalId = (int)($input['proposal_id'] ?? 0);
    $support = (int)($input['support'] ?? -1); // 1=a favor, 0=en contra
    $reason = trim($input['reason'] ?? '');
    
    if ($proposalId <= 0) {
        throw new Exception('ID de propuesta inválido');
    }
    
    if (!in_array($support, [0, 1])) {
        throw new Exception('Voto inválido. Usa 1 para A FAVOR o 0 para EN CONTRA');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Verificar smart account
    $stmt = $pdo->prepare('SELECT smart_account_address FROM smart_accounts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        throw new Exception('Necesitas una smart account para votar');
    }
    
    // Obtener propuesta
    $stmt = $pdo->prepare('
        SELECT status, voting_end, min_sphe_to_vote
        FROM governance_proposals 
        WHERE id = ?
    ');
    $stmt->execute([$proposalId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposal) {
        throw new Exception('Propuesta no encontrada');
    }
    
    if ($proposal['status'] !== 'active') {
        throw new Exception('Esta propuesta ya no está activa');
    }
    
    if (strtotime($proposal['voting_end']) < time()) {
        throw new Exception('El periodo de votación ha terminado');
    }
    
    // Obtener balance REAL de SPHE
    $stmt = $pdo->prepare('SELECT balance_sphe FROM wallet_balances WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $spheBalance = floatval($balance['balance_sphe'] ?? 0);
    $minRequired = floatval($proposal['min_sphe_to_vote']);
    
    if ($spheBalance < $minRequired) {
        throw new Exception("Necesitas al menos {$minRequired} SPHE para votar en esta propuesta");
    }
    
    // Verificar que no haya votado antes
    $stmt = $pdo->prepare('
        SELECT id FROM governance_votes 
        WHERE proposal_id = ? AND user_id = ?
    ');
    $stmt->execute([$proposalId, $userId]);
    if ($stmt->fetch()) {
        throw new Exception('Ya has votado en esta propuesta');
    }
    
    // Registrar voto (crear tabla si no existe)
    try {
        $stmt = $pdo->prepare('
            INSERT INTO governance_votes
            (proposal_id, user_id, support, voting_power, reason)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$proposalId, $userId, $support, $spheBalance, $reason]);
    } catch (PDOException $e) {
        // Si la tabla no existe, crearla
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS governance_votes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                proposal_id BIGINT NOT NULL,
                user_id INT NOT NULL,
                support TINYINT NOT NULL,
                voting_power DECIMAL(20,8) NOT NULL,
                reason TEXT,
                voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_vote (proposal_id, user_id),
                INDEX idx_proposal (proposal_id)
            )
        ');
        
        // Intentar de nuevo
        $stmt = $pdo->prepare('
            INSERT INTO governance_votes
            (proposal_id, user_id, support, voting_power, reason)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$proposalId, $userId, $support, $spheBalance, $reason]);
    }
    
    // Actualizar conteo en propuesta
    $column = $support === 1 ? 'total_votes_for' : 'total_votes_against';
    $stmt = $pdo->prepare("
        UPDATE governance_proposals 
        SET $column = $column + 1,
            total_sphe_voted = total_sphe_voted + ?
        WHERE id = ?
    ");
    $stmt->execute([$spheBalance, $proposalId]);
    
    echo json_encode([
        'success' => true,
        'message' => $support === 1 ? 'Voto A FAVOR registrado' : 'Voto EN CONTRA registrado',
        'voting_power_used' => $spheBalance
    ]);
    
} catch (Exception $e) {
    error_log('cast_vote error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
