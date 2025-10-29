<?php
/**
 * ============================================
 * QUEUE PROPOSAL TO TIMELOCK
 * ============================================
 * Endpoint: POST /api/governance/timelock-queue.php
 * Queue an approved proposal for timelock execution
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
require_once __DIR__ . '/helpers/governance-db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['operation_hash', 'proposal_id', 'target_address', 'salt', 'proposer', 'execution_eta'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Missing required field: $field"
            ]);
            exit();
        }
    }
    
    // Validate addresses
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $input['target_address'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid target address'
        ]);
        exit();
    }
    
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $input['proposer'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid proposer address'
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
    
    // Check if operation already queued
    $stmt = $mysqli->prepare("SELECT id FROM governance_timelock_queue WHERE operation_hash = ?");
    $stmt->bind_param("s", $input['operation_hash']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Operation already queued'
        ]);
        exit();
    }
    
    // Get delay from config
    $config_stmt = $mysqli->prepare("SELECT config_value FROM governance_timelock_config WHERE config_key = 'min_delay_seconds'");
    $config_stmt->execute();
    $config_result = $config_stmt->get_result();
    $config_row = $config_result->fetch_assoc();
    $min_delay = $config_row ? intval($config_row['config_value']) : 172800;
    
    // Calculate execution ETA
    $delay = isset($input['delay_seconds']) ? intval($input['delay_seconds']) : $min_delay;
    if ($delay < $min_delay) {
        $delay = $min_delay;
    }
    
    // Insert into queue
    $insert_stmt = $mysqli->prepare("
        INSERT INTO governance_timelock_queue (
            operation_hash,
            proposal_id,
            target_address,
            value_wei,
            call_data,
            predecessor_hash,
            salt,
            proposer,
            description,
            category,
            delay_seconds,
            execution_eta,
            is_batch,
            batch_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $value_wei = $input['value_wei'] ?? '0';
    $call_data = $input['call_data'] ?? null;
    $predecessor = $input['predecessor_hash'] ?? null;
    $description = $input['description'] ?? '';
    $category = $input['category'] ?? 'other';
    $execution_eta = $input['execution_eta'];
    $is_batch = isset($input['is_batch']) ? boolval($input['is_batch']) : false;
    $batch_data = isset($input['batch_data']) ? json_encode($input['batch_data']) : null;
    
    $insert_stmt->bind_param(
        "ssssssssssissb",
        $input['operation_hash'],
        $input['proposal_id'],
        $input['target_address'],
        $value_wei,
        $call_data,
        $predecessor,
        $input['salt'],
        $input['proposer'],
        $description,
        $category,
        $delay,
        $execution_eta,
        $is_batch,
        $batch_data
    );
    
    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to queue operation: ' . $insert_stmt->error);
    }
    
    $queue_id = $mysqli->insert_id;
    
    // Log event
    $event_stmt = $mysqli->prepare("
        INSERT INTO governance_timelock_events (
            operation_hash,
            event_type,
            actor_address,
            event_data
        ) VALUES (?, 'queued', ?, ?)
    ");
    
    $event_data = json_encode([
        'proposal_id' => $input['proposal_id'],
        'execution_eta' => $execution_eta,
        'delay_seconds' => $delay
    ]);
    
    $event_stmt->bind_param("sss", $input['operation_hash'], $input['proposer'], $event_data);
    $event_stmt->execute();
    
    // Success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Proposal queued for timelock execution',
        'data' => [
            'queue_id' => $queue_id,
            'operation_hash' => $input['operation_hash'],
            'execution_eta' => $execution_eta,
            'delay_seconds' => $delay,
            'status' => 'queued'
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
