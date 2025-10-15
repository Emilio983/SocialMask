<?php
/**
 * CHECK PAYMENT API
 * Verifica si un usuario puede responder una encuesta
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Incluir configuración
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../config/blockchain_config.php';

if (!isset($_GET['survey_id']) || !isset($_GET['wallet_address'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'survey_id and wallet_address are required']);
    exit;
}

$survey_id = intval($_GET['survey_id']);
$wallet_address = strtolower(trim($_GET['wallet_address']));

try {
    // Llamar stored procedure para verificar acceso
    $stmt = $pdo->prepare("CALL CheckUserSurveyAccess(?, ?)");
    $stmt->execute([$survey_id, $wallet_address]);
    $access = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$access) {
        // Usuario no ha pagado
        echo json_encode([
            'success' => false,
            'can_respond' => false,
            'access_status' => 'not_paid',
            'has_paid' => false,
            'message' => 'Debes pagar para participar en esta encuesta'
        ]);
        exit;
    }

    $access_status = $access['access_status'];
    $has_responded = boolval($access['has_responded']);
    $survey_status = $access['survey_status'];
    $close_date = $access['close_date'];

    // Determinar si puede responder
    $can_respond = ($access_status === 'can_respond');

    // Mensaje según estado
    $messages = [
        'already_responded' => 'Ya has respondido esta encuesta',
        'survey_not_active' => 'La encuesta no está activa',
        'survey_closed' => 'La encuesta ya cerró',
        'can_respond' => 'Puedes responder ahora'
    ];

    echo json_encode([
        'success' => true,
        'can_respond' => $can_respond,
        'access_status' => $access_status,
        'has_paid' => true,
        'has_responded' => $has_responded,
        'survey_status' => $survey_status,
        'close_date' => $close_date,
        'message' => $messages[$access_status] ?? ''
    ]);

} catch (PDOException $e) {
    error_log("Check payment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check payment status'
    ]);
}
?>
