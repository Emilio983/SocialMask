<?php
/**
 * CREATE SURVEY API
 * Crea una nueva encuesta con precio en SPHE
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir configuración
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../config/blockchain_config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

// Validar datos requeridos
$required = ['title', 'price', 'close_date'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Field '{$field}' is required"]);
        exit;
    }
}

$title = trim($input['title']);
$description = isset($input['description']) ? trim($input['description']) : '';
$price = floatval($input['price']);
$close_date = $input['close_date'];
$max_participants = isset($input['max_participants']) ? intval($input['max_participants']) : null;
$winners_selection_type = isset($input['winners_selection_type']) ? $input['winners_selection_type'] : 'manual';
$winner_distribution = isset($input['winner_distribution']) ? json_encode($input['winner_distribution']) : null;

// Validaciones
if ($price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Price must be greater than 0']);
    exit;
}

if (strlen($title) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Title must be at least 10 characters']);
    exit;
}

// Validar fecha de cierre
$close_timestamp = strtotime($close_date);
if ($close_timestamp === false || $close_timestamp <= time()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Close date must be in the future']);
    exit;
}

// Validar tipo de selección
$valid_types = ['manual', 'random', 'automatic'];
if (!in_array($winners_selection_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid winners_selection_type']);
    exit;
}

try {
    // Insertar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO surveys (
            title,
            description,
            price,
            token_address,
            close_date,
            max_participants,
            winners_selection_type,
            winner_distribution,
            created_by,
            status
        ) VALUES (
            :title,
            :description,
            :price,
            :token_address,
            :close_date,
            :max_participants,
            :winners_selection_type,
            :winner_distribution,
            :created_by,
            'draft'
        )
    ");

    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':price' => $price,
        ':token_address' => SPHE_TOKEN_ADDRESS,
        ':close_date' => date('Y-m-d H:i:s', $close_timestamp),
        ':max_participants' => $max_participants,
        ':winners_selection_type' => $winners_selection_type,
        ':winner_distribution' => $winner_distribution,
        ':created_by' => $user_id
    ]);

    $survey_id = $pdo->lastInsertId();

    // Obtener la encuesta creada
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            u.username as creator_username,
            u.wallet_address as creator_wallet
        FROM surveys s
        LEFT JOIN users u ON s.created_by = u.user_id
        WHERE s.id = :survey_id
    ");
    $stmt->execute([':survey_id' => $survey_id]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);

    // Decodificar JSON
    if ($survey['winner_distribution']) {
        $survey['winner_distribution'] = json_decode($survey['winner_distribution'], true);
    }

    echo json_encode([
        'success' => true,
        'survey' => $survey,
        'message' => 'Survey created successfully. Remember to activate it after deploying to blockchain.'
    ]);

} catch (PDOException $e) {
    error_log("Create survey error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create survey'
    ]);
}
