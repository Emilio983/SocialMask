<?php
/**
 * ============================================
 * LIST MULTISIG PROPOSALS
 * ============================================
 * Endpoint: GET /api/governance/multisig-list.php
 * Get list of multi-sig proposals with filters
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'sphera';

try {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Get filters from query params
    $status = $_GET['status'] ?? null;
    $proposalType = $_GET['type'] ?? null;
    $proposer = isset($_GET['proposer']) ? strtolower($_GET['proposer']) : null;
    $signer = isset($_GET['signer']) ? strtolower($_GET['signer']) : null;
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    // Build query
    $where = [];
    $params = [];
    $types = '';
    
    if ($status) {
        $where[] = "p.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($proposalType) {
        $where[] = "p.proposal_type = ?";
        $params[] = $proposalType;
        $types .= 's';
    }
    
    if ($proposer) {
        $where[] = "p.proposer_address = ?";
        $params[] = $proposer;
        $types .= 's';
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get proposals
    $query = "
        SELECT 
            p.*,
            GROUP_CONCAT(DISTINCT s.signer_address) as signers,
            " . ($signer ? "MAX(CASE WHEN s.signer_address = '$signer' THEN 1 ELSE 0 END) as has_signed" : "0 as has_signed") . "
        FROM governance_multisig_proposals p
        LEFT JOIN governance_multisig_signatures s ON p.proposal_id = s.proposal_id AND s.is_revoked = FALSE
        $whereClause
        GROUP BY p.proposal_id
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $mysqli->prepare($query);
    
    if (!empty($params)) {
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $proposals = [];
    while ($row = $result->fetch_assoc()) {
        $proposals[] = [
            'proposal_id' => intval($row['proposal_id']),
            'proposal_type' => $row['proposal_type'],
            'title' => $row['title'],
            'description' => $row['description'],
            'proposer_address' => $row['proposer_address'],
            'target_contract' => $row['target_contract'],
            'function_data' => $row['function_data'],
            'eth_value' => $row['eth_value'],
            'status' => $row['status'],
            'signature_count' => intval($row['signature_count']),
            'required_signatures' => intval($row['required_signatures']),
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at'],
            'executed_at' => $row['executed_at'],
            'signers' => $row['signers'] ? explode(',', $row['signers']) : [],
            'has_signed' => (bool)$row['has_signed']
        ];
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM governance_multisig_proposals p $whereClause";
    $countStmt = $mysqli->prepare($countQuery);
    
    if (!empty($where)) {
        array_pop($params); // Remove offset
        array_pop($params); // Remove limit
        $types = substr($types, 0, -2); // Remove 'ii'
        $countStmt->bind_param($types, ...$params);
    }
    
    $countStmt->execute();
    $totalRow = $countStmt->get_result()->fetch_assoc();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'proposals' => $proposals,
        'total' => intval($totalRow['total']),
        'limit' => $limit,
        'offset' => $offset
    ]);
    
    $mysqli->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
