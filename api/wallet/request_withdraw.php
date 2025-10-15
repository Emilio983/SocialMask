<?php
/**
 * API Proxy: Solicitar Retiro SPHE→USDT→Externa
 * Endpoint: POST /api/wallet/request_withdraw.php
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

if (!$input || !isset($input['spheAmount']) || !isset($input['destinationAddress'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Validar dirección Ethereum
$address = $input['destinationAddress'];
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dirección Ethereum inválida']);
    exit;
}

// Obtener smart account address desde la tabla smart_accounts
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
    echo json_encode(['success' => false, 'message' => 'Smart account no encontrada']);
    exit;
}

// Preparar datos para backend Node.js
$payload = [
    'userId' => $_SESSION['user_id'],
    'smartAccountAddress' => $user['smart_account_address'],
    'destinationAddress' => $address,
    'amountSphe' => $input['spheAmount']
];

// Llamar al backend Node.js
$ch = curl_init('http://localhost:3088/withdraw');
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
