<?php
/**
 * ============================================
 * EXECUTE QUEUED PROPOSAL
 * ============================================
 * Endpoint: POST /api/governance/timelock-execute.php
 * Execute a proposal after timelock has expired
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../../config/connection.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['operation_hash']) || !isset($input['executor'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing operation_hash or executor'
        ]);
        exit();
    }
    
    // Validate addresses
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $input['executor'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid executor address'
        ]);
        exit();
    }
    
    // Validate operation hash
    if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $input['operation_hash'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid operation hash'
        ]);
        exit();
    }
    
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Get operation from queue
    $stmt = $mysqli->prepare("
        SELECT * FROM governance_timelock_queue 
        WHERE operation_hash = ?
    ");
    $stmt->bind_param("s", $input['operation_hash']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Operation not found'
        ]);
        exit();
    }
    
    $operation = $result->fetch_assoc();
    
    // Check if already executed
    if ($operation['status'] === 'executed') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Operation already executed',
            'data' => [
                'executed_at' => $operation['executed_at'],
                'executed_by' => $operation['executed_by'],
                'tx_hash' => $operation['executed_tx_hash']
            ]
        ]);
        exit();
    }
    
    // Check if cancelled
    if ($operation['status'] === 'cancelled') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Operation was cancelled',
            'data' => [
                'cancelled_at' => $operation['cancelled_at'],
                'cancelled_by' => $operation['cancelled_by'],
                'reason' => $operation['cancellation_reason']
            ]
        ]);
        exit();
    }
    
    // Check if timelock has expired (ready to execute)
    $now = new DateTime();
    $eta = new DateTime($operation['execution_eta']);
    
    if ($now < $eta) {
        $diff = $now->diff($eta);
        $timeRemaining = '';
        if ($diff->d > 0) $timeRemaining .= $diff->d . ' days, ';
        $timeRemaining .= $diff->h . ' hours, ' . $diff->i . ' minutes';
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Timelock not expired yet',
            'data' => [
                'execution_eta' => $operation['execution_eta'],
                'time_remaining' => $timeRemaining,
                'status' => 'queued'
            ]
        ]);
        exit();
    }
    
    // Update operation status
    $tx_hash = $input['tx_hash'] ?? null;
    
    $update_stmt = $mysqli->prepare("
        UPDATE governance_timelock_queue 
        SET status = 'executed',
            executed_at = NOW(),
            executed_by = ?,
            executed_tx_hash = ?
        WHERE operation_hash = ?
    ");
    
    $update_stmt->bind_param("sss", $input['executor'], $tx_hash, $input['operation_hash']);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update operation status');
    }
    
    // Log event
    $event_stmt = $mysqli->prepare("
        INSERT INTO governance_timelock_events (
            operation_hash,
            event_type,
            actor_address,
            event_data,
            transaction_hash
        ) VALUES (?, 'executed', ?, ?, ?)
    ");
    
    $event_data = json_encode([
        'proposal_id' => $operation['proposal_id'],
        'execution_time' => date('Y-m-d H:i:s')
    ]);
    
    $event_stmt->bind_param("ssss", $input['operation_hash'], $input['executor'], $event_data, $tx_hash);
    $event_stmt->execute();
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Proposal executed successfully',
        'data' => [
            'operation_hash' => $input['operation_hash'],
            'proposal_id' => $operation['proposal_id'],
            'executed_at' => date('Y-m-d H:i:s'),
            'executed_by' => $input['executor'],
            'tx_hash' => $tx_hash,
            'status' => 'executed'
        ]
    ]);
    
    $mysqli->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
