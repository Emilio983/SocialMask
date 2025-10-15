<?php
require_once '../../config/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Parámetros de query
$status = $_GET['status'] ?? 'active';
$limit = min((int)($_GET['limit'] ?? 20), 100);
$offset = (int)($_GET['offset'] ?? 0);
$userId = (int)($_GET['user_id'] ?? 0);
$campaignId = (int)($_GET['campaign_id'] ?? 0);

try {
    // Si se solicita una campaña específica
    if ($campaignId > 0) {
        $sql = "
            SELECT 
                c.*,
                u.username,
                u.profile_pic,
                COUNT(DISTINCT d.id) as donation_count,
                COALESCE(SUM(CASE WHEN d.status = 'confirmed' THEN d.amount ELSE 0 END), 0) as total_raised
            FROM donation_campaigns c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN donations d ON c.id = d.campaign_id
            WHERE c.id = ?
            GROUP BY c.id
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $campaignId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $campaigns = [];
        while ($row = $result->fetch_assoc()) {
            $campaigns[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'campaigns' => $campaigns,
            'count' => count($campaigns)
        ]);
        exit;
    }
    
    // Consulta general
    $sql = "
        SELECT 
            c.*,
            u.username,
            u.profile_pic,
            COUNT(DISTINCT d.id) as donation_count,
            COALESCE(SUM(CASE WHEN d.status = 'confirmed' THEN d.amount ELSE 0 END), 0) as total_raised
        FROM donation_campaigns c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN donations d ON c.id = d.campaign_id
        WHERE 1=1
    ";
    
    $params = [];
    $types = "";
    
    // Filtro por estado
    if ($status && $status !== 'all') {
        $sql .= " AND c.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Filtro por usuario
    if ($userId > 0) {
        $sql .= " AND c.user_id = ?";
        $params[] = $userId;
        $types .= "i";
    }
    
    $sql .= "
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $campaigns = [];
    while ($row = $result->fetch_assoc()) {
        // Actualizar raised_amount si es diferente del calculado
        if ($row['raised_amount'] != $row['total_raised']) {
            $updateStmt = $conn->prepare("UPDATE donation_campaigns SET raised_amount = ? WHERE id = ?");
            $updateStmt->bind_param("di", $row['total_raised'], $row['id']);
            $updateStmt->execute();
            $row['raised_amount'] = $row['total_raised'];
        }
        
        unset($row['total_raised']); // No enviar campo temporal
        $campaigns[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'campaigns' => $campaigns,
        'count' => count($campaigns),
        'total' => count($campaigns) // TODO: Implementar count total sin LIMIT
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
