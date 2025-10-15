<?php
/**
 * API Proxy: Ejecutar Acción Gasless
 * Endpoint: POST /api/wallet/gasless_action.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../check_session.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Leer body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['actionType']) || !isset($input['amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Obtener smart account del usuario emisor desde la tabla smart_accounts
require_once __DIR__ . '/../../config/connection.php';

$stmt = $conn->prepare("
    SELECT sa.smart_account_address
    FROM smart_accounts sa
    WHERE sa.user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !$user['smart_account_address']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Smart account no encontrada para el usuario']);
    exit;
}

$smartAccountAddress = $user['smart_account_address'];

// Si viene recipientId, obtener su smart account address
$recipientAddress = '0x0000000000000000000000000000000000000000'; // Default para acciones sin recipiente (VOTE, DONATION)

if (isset($input['recipientId']) && !empty($input['recipientId'])) {
    $recipientId = intval($input['recipientId']);
    $stmt = $conn->prepare("
        SELECT sa.smart_account_address
        FROM smart_accounts sa
        WHERE sa.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $recipientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipient = $result->fetch_assoc();

    if ($recipient && $recipient['smart_account_address']) {
        $recipientAddress = $recipient['smart_account_address'];
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Recipiente no encontrado o sin smart account']);
        exit;
    }
}

// Preparar datos para backend Node.js
$payload = [
    'userId' => $_SESSION['user_id'],
    'smartAccountAddress' => $smartAccountAddress,
    'recipient' => $recipientAddress,
    'actionType' => $input['actionType'],
    'amount' => $input['amount'],
    'metadata' => isset($input['metadata']) ? json_encode($input['metadata']) : '{}'
];

// Llamar al backend Node.js
$ch = curl_init('http://localhost:3088/actions/execute');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Retornar respuesta
http_response_code($httpCode);
echo $response ?: json_encode(['success' => false, 'message' => 'Error comunicando con backend']);
