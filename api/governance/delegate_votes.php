<?php
/**
 * API: Delegar votos
 * POST /api/governance/delegate_votes.php
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
    $delegateAddress = trim($input['delegate_address'] ?? '');
    
    if (empty($delegateAddress) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $delegateAddress)) {
        throw new Exception('Dirección de delegado inválida');
    }
    
    // Get user's smart account
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT smart_account_address FROM smart_accounts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        throw new Exception('Smart account no encontrada');
    }
    
    $delegatorAddress = $account['smart_account_address'];
    
    if (strtolower($delegatorAddress) === strtolower($delegateAddress)) {
        throw new Exception('No puedes delegarte a ti mismo');
    }
    
    // Revoke existing delegation
    $stmt = $pdo->prepare('UPDATE governance_delegations SET revoked_at = NOW() WHERE delegator_address = ?');
    $stmt->execute([$delegatorAddress]);
    
    // Create new delegation
    $votingPower = '1000'; // Mock
    $txHash = '0x' . bin2hex(random_bytes(32));
    
    $stmt = $pdo->prepare('
        INSERT INTO governance_delegations
        (delegator_address, delegate_address, delegator_user_id, voting_power, tx_hash)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$delegatorAddress, $delegateAddress, $userId, $votingPower, $txHash]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Votos delegados exitosamente',
        'tx_hash' => $txHash
    ]);
    
} catch (Exception $e) {
    error_log('delegate_votes error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
