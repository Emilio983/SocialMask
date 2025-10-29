<?php
/**
 * SUBMIT SURVEY RESPONSE API
 * Guarda las respuestas de un usuario a una encuesta
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

// Validar datos requeridos
$required = ['survey_id', 'wallet_address', 'responses'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Field '{$field}' is required"]);
        exit;
    }
}

$survey_id = intval($input['survey_id']);
$wallet_address = strtolower(trim($input['wallet_address']));
$responses = $input['responses']; // Debe ser array/object
$selected_answer = $input['selected_answer'] ?? null; // Para encuestas tipo apuesta (A o B)

// Validar formato de wallet
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid wallet address format']);
    exit;
}

try {
    // Verificar que el usuario haya pagado y pueda responder
    $stmt = $pdo->prepare("
        SELECT id, has_responded, user_id
        FROM user_survey_access
        WHERE survey_id = ?
          AND wallet_address = ?
    ");
    $stmt->execute([$survey_id, $wallet_address]);
    $access = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$access) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'No tienes acceso a esta encuesta. Debes pagar primero.'
        ]);
        exit;
    }

    if ($access['has_responded']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Ya has respondido esta encuesta'
        ]);
        exit;
    }

    $user_id = $access['user_id'];

    // Verificar que la encuesta esté activa
    $stmt = $pdo->prepare("
        SELECT status, close_date
        FROM surveys
        WHERE id = ?
    ");
    $stmt->execute([$survey_id]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$survey) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Survey not found']);
        exit;
    }

    if ($survey['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey is not active']);
        exit;
    }

    if (strtotime($survey['close_date']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey has closed']);
        exit;
    }

    // Insertar respuesta
    $stmt = $pdo->prepare("
        INSERT INTO survey_responses
        (survey_id, wallet_address, user_id, payment_id, responses, selected_answer, ip_address, user_agent, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    // Obtener payment_id del access
    $stmt_payment = $pdo->prepare("SELECT payment_id FROM user_survey_access WHERE id = ?");
    $stmt_payment->execute([$access['id']]);
    $payment_id = $stmt_payment->fetchColumn();

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt->execute([
        $survey_id,
        $wallet_address,
        $user_id,
        $payment_id,
        json_encode($responses),
        $selected_answer,
        $ip_address,
        $user_agent
    ]);

    $response_id = $pdo->lastInsertId();

    // El trigger automáticamente actualizará user_survey_access.has_responded = TRUE

    // Opcional: Crear notificación
    if ($user_id) {
        try {
            $stmt_notif = $pdo->prepare("
                INSERT INTO notifications
                (user_id, type, title, message, created_at)
                VALUES (?, 'survey', 'Respuesta Enviada', ?, NOW())
            ");

            $message = "Tu respuesta para la encuesta ha sido registrada. Espera a que finalice para ver si ganaste.";
            $stmt_notif->execute([$user_id, $message]);
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'response_id' => $response_id,
        'message' => 'Respuesta guardada exitosamente. Espera a que la encuesta finalice para ver los resultados.'
    ]);

} catch (PDOException $e) {
    error_log("Submit survey response error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to submit response'
    ]);
}
?>
