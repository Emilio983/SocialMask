<?php
/**
 * ============================================
 * CANCEL QUEUED PROPOSAL
 * ============================================
 * Endpoint: POST /api/governance/timelock-cancel.php
 * Cancel a queued proposal before execution
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
    if (!isset($input['operation_hash']) || !isset($input['canceller'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing operation_hash or canceller'
        ]);
        exit();
    }
    
    // Validate addresses
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $input['canceller'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid canceller address'
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
            'error' => 'Cannot cancel executed operation',
            'data' => [
                'executed_at' => $operation['executed_at'],
                'executed_by' => $operation['executed_by']
            ]
        ]);
        exit();
    }
    
    // Check if already cancelled
    if ($operation['status'] === 'cancelled') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Operation already cancelled',
            'data' => [
                'cancelled_at' => $operation['cancelled_at'],
                'cancelled_by' => $operation['cancelled_by'],
                'reason' => $operation['cancellation_reason']
            ]
        ]);
        exit();
    }
    
    // Update operation status
    $reason = $input['reason'] ?? 'Cancelled by user';
    $is_emergency = isset($input['is_emergency']) ? boolval($input['is_emergency']) : false;
    
    $update_stmt = $mysqli->prepare("
        UPDATE governance_timelock_queue 
        SET status = 'cancelled',
            cancelled_at = NOW(),
            cancelled_by = ?,
            cancellation_reason = ?
        WHERE operation_hash = ?
    ");
    
    $update_stmt->bind_param("sss", $input['canceller'], $reason, $input['operation_hash']);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to cancel operation');
    }
    
    // Log event
    $event_type = $is_emergency ? 'emergency_cancel' : 'cancelled';
    $event_stmt = $mysqli->prepare("
        INSERT INTO governance_timelock_events (
            operation_hash,
            event_type,
            actor_address,
            event_data
        ) VALUES (?, ?, ?, ?)
    ");
    
    $event_data = json_encode([
        'proposal_id' => $operation['proposal_id'],
        'reason' => $reason,
        'is_emergency' => $is_emergency,
        'cancellation_time' => date('Y-m-d H:i:s')
    ]);
    
    $event_stmt->bind_param("ssss", $input['operation_hash'], $event_type, $input['canceller'], $event_data);
    $event_stmt->execute();
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Proposal cancelled successfully',
        'data' => [
            'operation_hash' => $input['operation_hash'],
            'proposal_id' => $operation['proposal_id'],
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancelled_by' => $input['canceller'],
            'reason' => $reason,
            'is_emergency' => $is_emergency,
            'status' => 'cancelled'
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
