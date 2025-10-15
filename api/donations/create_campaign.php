<?php
require_once '../../config/connection.php';
require_once '../helpers/TokenValidator.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Validar JWT
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

try {
    $decoded = TokenValidator::validate($token);
    if (!$decoded) {
        throw new Exception('Invalid token');
    }
    $userId = $decoded->user_id;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

$title = $input['title'] ?? '';
$description = $input['description'] ?? '';
$goalAmount = $input['goal_amount'] ?? 0;
$endDate = $input['end_date'] ?? null;

// Validar datos
if (empty($title) || strlen($title) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid title']);
    exit;
}

if (empty($description)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Description is required']);
    exit;
}

if ($goalAmount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Goal amount must be greater than 0']);
    exit;
}

// Validar end_date
if ($endDate) {
    $endDateTime = strtotime($endDate);
    if ($endDateTime < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'End date must be in the future']);
        exit;
    }
}

try {
    // Insertar campaña
    $stmt = $conn->prepare("
        INSERT INTO donation_campaigns 
        (user_id, title, description, goal_amount, raised_amount, end_date, status, created_at)
        VALUES (?, ?, ?, ?, 0, ?, 'active', NOW())
    ");
    
    $stmt->bind_param(
        "issds", 
        $userId, 
        $title, 
        $description, 
        $goalAmount, 
        $endDate
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create campaign: ' . $stmt->error);
    }
    
    $campaignId = $conn->insert_id;
    
    // Obtener la campaña creada
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.profile_pic
        FROM donation_campaigns c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    $campaign = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'campaign' => $campaign,
        'message' => 'Campaign created successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
