<?php
/**
 * API: Crear Contenido de Pago
 * Endpoint: POST /api/paywall/create_content.php
 * 
 * Crea contenido de pago y lo registra en el smart contract
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Verificar autenticación
$user = authenticate();
if (!$user) {
    sendError('Unauthorized', 401);
}

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);

// Validar campos requeridos
$required = ['title', 'price', 'content_type', 'contract_content_id'];
$missing = [];

foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    sendError('Missing required fields: ' . implode(', ', $missing), 400);
}

// Validar datos
$title = trim($input['title']);
$description = isset($input['description']) ? trim($input['description']) : '';
$price = $input['price'];
$content_type = $input['content_type'];
$contract_content_id = (int)$input['contract_content_id'];
$content_url = isset($input['content_url']) ? trim($input['content_url']) : null;
$preview_url = isset($input['preview_url']) ? trim($input['preview_url']) : null;
$preview_text = isset($input['preview_text']) ? trim($input['preview_text']) : null;

// Validaciones
if (strlen($title) < 3 || strlen($title) > 255) {
    sendError('Title must be between 3 and 255 characters', 400);
}

if (!is_numeric($price) || $price <= 0) {
    sendError('Price must be a positive number', 400);
}

$valid_types = ['post', 'video', 'image', 'article', 'audio'];
if (!in_array($content_type, $valid_types)) {
    sendError('Invalid content type. Must be: ' . implode(', ', $valid_types), 400);
}

if ($contract_content_id <= 0) {
    sendError('Invalid contract content ID', 400);
}

try {
    $db = getConnection();
    
    // Verificar que el contract_content_id no exista ya
    $stmt = $db->prepare("
        SELECT id FROM paywall_content 
        WHERE contract_content_id = ? AND status != 'deleted'
    ");
    $stmt->execute([$contract_content_id]);
    
    if ($stmt->fetch()) {
        sendError('Contract content ID already exists', 409);
    }
    
    // Insertar contenido
    $stmt = $db->prepare("
        INSERT INTO paywall_content (
            user_id, 
            contract_content_id, 
            title, 
            description, 
            price, 
            content_type,
            content_url,
            preview_url,
            preview_text,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    
    $stmt->execute([
        $user['id'],
        $contract_content_id,
        $title,
        $description,
        $price,
        $content_type,
        $content_url,
        $preview_url,
        $preview_text
    ]);
    
    $content_id = $db->lastInsertId();
    
    // Inicializar stats
    $stmt = $db->prepare("
        INSERT INTO paywall_stats (content_id, views, purchases, revenue)
        VALUES (?, 0, 0, 0)
    ");
    $stmt->execute([$content_id]);
    
    // Obtener contenido creado
    $stmt = $db->prepare("
        SELECT 
            pc.*,
            u.username as creator_username,
            u.wallet_address as creator_wallet
        FROM paywall_content pc
        JOIN users u ON pc.user_id = u.id
        WHERE pc.id = ?
    ");
    $stmt->execute([$content_id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log de actividad
    $stmt = $db->prepare("
        INSERT INTO activity_log (user_id, action, details)
        VALUES (?, 'paywall_create', ?)
    ");
    $stmt->execute([
        $user['id'],
        json_encode([
            'content_id' => $content_id,
            'contract_content_id' => $contract_content_id,
            'title' => $title,
            'price' => $price
        ])
    ]);
    
    sendSuccess([
        'message' => 'Content created successfully',
        'content' => $content
    ], 201);
    
} catch (PDOException $e) {
    error_log("Database error in create_content: " . $e->getMessage());
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error in create_content: " . $e->getMessage());
    sendError('Server error: ' . $e->getMessage(), 500);
}
