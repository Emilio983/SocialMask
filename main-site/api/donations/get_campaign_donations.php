<?php
require_once '../../config/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$campaignId = (int)($_GET['campaign_id'] ?? 0);
$limit = min((int)($_GET['limit'] ?? 50), 100);
$offset = (int)($_GET['offset'] ?? 0);

if ($campaignId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid campaign_id']);
    exit;
}

try {
    // Verificar que la campaña existe
    $stmt = $conn->prepare("SELECT id FROM donation_campaigns WHERE id = ?");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Campaign not found']);
        exit;
    }
    
    // Obtener donaciones
    $stmt = $conn->prepare("
        SELECT 
            d.*,
            u.username,
            u.profile_pic
        FROM donations d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.campaign_id = ? AND d.status = 'confirmed'
        ORDER BY d.confirmed_at DESC, d.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("iii", $campaignId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $donations = [];
    $totalAmount = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Ocultar parte de la dirección por privacidad
        $row['donor_address_short'] = substr($row['donor_address'], 0, 6) . '...' . substr($row['donor_address'], -4);
        
        $donations[] = $row;
        $totalAmount += (float)$row['amount'];
    }
    
    // Obtener totales de la campaña
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_donations,
            COALESCE(SUM(amount), 0) as total_raised
        FROM donations
        WHERE campaign_id = ? AND status = 'confirmed'
    ");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'donations' => $donations,
        'total_amount' => $totalAmount,
        'total_raised' => (float)$totals['total_raised'],
        'total_count' => (int)$totals['total_donations'],
        'showing' => count($donations)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
