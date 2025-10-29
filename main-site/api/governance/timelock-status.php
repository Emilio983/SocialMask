<?php
/**
 * ============================================
 * GET TIMELOCK STATUS
 * ============================================
 * Endpoint: GET /api/governance/timelock-status.php
 * Get status of queued proposals with countdown
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../../config/connection.php';

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Get filters from query params
    $operation_hash = $_GET['operation_hash'] ?? null;
    $proposal_id = $_GET['proposal_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    // Build query
    $query = "SELECT * FROM governance_timelock_queue WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($operation_hash) {
        $query .= " AND operation_hash = ?";
        $params[] = $operation_hash;
        $types .= "s";
    }
    
    if ($proposal_id) {
        $query .= " AND proposal_id = ?";
        $params[] = $proposal_id;
        $types .= "s";
    }
    
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // Execute query
    $stmt = $mysqli->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $operations = [];
    $now = new DateTime();
    
    while ($row = $result->fetch_assoc()) {
        $eta = new DateTime($row['execution_eta']);
        
        // Calculate time remaining
        $time_remaining = null;
        $is_ready = false;
        
        if ($now < $eta) {
            $diff = $now->diff($eta);
            $time_remaining = [
                'days' => $diff->d,
                'hours' => $diff->h,
                'minutes' => $diff->i,
                'seconds' => $diff->s,
                'total_seconds' => ($diff->days * 86400) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s,
                'formatted' => ($diff->d > 0 ? $diff->d . ' days, ' : '') . 
                               $diff->h . 'h ' . $diff->i . 'm ' . $diff->s . 's'
            ];
        } else {
            $is_ready = true;
        }
        
        $operations[] = [
            'id' => $row['id'],
            'operation_hash' => $row['operation_hash'],
            'proposal_id' => $row['proposal_id'],
            'target_address' => $row['target_address'],
            'value_wei' => $row['value_wei'],
            'call_data' => $row['call_data'],
            'proposer' => $row['proposer'],
            'description' => $row['description'],
            'category' => $row['category'],
            'delay_seconds' => $row['delay_seconds'],
            'queued_at' => $row['queued_at'],
            'execution_eta' => $row['execution_eta'],
            'status' => $row['status'],
            'is_ready' => $is_ready,
            'time_remaining' => $time_remaining,
            'is_batch' => (bool)$row['is_batch'],
            'batch_data' => $row['batch_data'] ? json_decode($row['batch_data']) : null,
            'executed_at' => $row['executed_at'],
            'executed_by' => $row['executed_by'],
            'executed_tx_hash' => $row['executed_tx_hash'],
            'cancelled_at' => $row['cancelled_at'],
            'cancelled_by' => $row['cancelled_by'],
            'cancellation_reason' => $row['cancellation_reason'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM governance_timelock_queue WHERE 1=1";
    if ($operation_hash) $count_query .= " AND operation_hash = '$operation_hash'";
    if ($proposal_id) $count_query .= " AND proposal_id = '$proposal_id'";
    if ($status) $count_query .= " AND status = '$status'";
    
    $count_result = $mysqli->query($count_query);
    $total = $count_result->fetch_assoc()['total'];
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_operations,
            SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued_count,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_count,
            SUM(CASE WHEN status = 'executed' THEN 1 ELSE 0 END) as executed_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
        FROM governance_timelock_queue
    ";
    $stats_result = $mysqli->query($stats_query);
    $stats = $stats_result->fetch_assoc();
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'operations' => $operations,
            'pagination' => [
                'total' => intval($total),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ],
            'statistics' => [
                'total_operations' => intval($stats['total_operations']),
                'queued' => intval($stats['queued_count']),
                'ready' => intval($stats['ready_count']),
                'executed' => intval($stats['executed_count']),
                'cancelled' => intval($stats['cancelled_count'])
            ]
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
