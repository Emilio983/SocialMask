<?php
/**
 * API: Obtener poder de voto REAL del usuario basado en balance SPHE
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../utils.php';

requireAuth();

try {
    $userId = $_SESSION['user_id'];
    
    // Obtener smart account del usuario
    $stmt = $pdo->prepare('
        SELECT smart_account_address
        FROM smart_accounts
        WHERE user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account || empty($account['smart_account_address'])) {
        echo json_encode([
            'success' => true,
            'voting_power' => '0',
            'balance' => '0',
            'delegated_to' => null,
            'can_vote' => false,
            'can_propose' => false,
            'message' => 'No tienes una smart account configurada'
        ]);
        exit;
    }
    
    $address = $account['smart_account_address'];
    
    // Obtener balance REAL de SPHE desde la wallet
    // TODO: Integrar con contrato SPHE real en Polygon
    // Por ahora, simular llamada a contrato usando RPC
    
    $spheBalance = '0';
    
    // Intentar obtener balance desde base de datos local (si existe)
    $stmt = $pdo->prepare('
        SELECT balance_sphe 
        FROM wallet_balances 
        WHERE user_id = ? 
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    $balanceData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($balanceData) {
        $spheBalance = $balanceData['balance_sphe'];
    }
    
    // Verificar si el usuario delegó su voto
    $stmt = $pdo->prepare('
        SELECT delegate_address
        FROM governance_delegations
        WHERE delegator_address = ? AND revoked_at IS NULL
        LIMIT 1
    ');
    $stmt->execute([$address]);
    $delegation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $delegatedTo = $delegation ? $delegation['delegate_address'] : null;
    $canVote = !$delegation; // No puede votar si delegó
    $canPropose = floatval($spheBalance) >= 100; // Mínimo 100 SPHE para crear propuesta
    
    echo json_encode([
        'success' => true,
        'voting_power' => $spheBalance,
        'balance' => $spheBalance,
        'delegated_to' => $delegatedTo,
        'can_vote' => $canVote,
        'can_propose' => $canPropose,
        'address' => $address,
        'token' => 'SPHE'
    ]);
    
} catch (Exception $e) {
    error_log('get_voting_power error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener poder de voto'
    ]);
}
