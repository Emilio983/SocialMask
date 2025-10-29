<?php
/**
 * API Proxy: Obtener Historial de Acciones Gasless
 * Endpoint: GET /api/wallet/gasless_history.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../check_session.php';

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$user_id = $_SESSION['user_id'];

// Obtener parámetros de query
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Llamar al backend Node.js
$ch = curl_init("http://localhost:3088/actions/history?userId={$user_id}&limit={$limit}&page={$page}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Retornar respuesta
http_response_code($httpCode);
echo $response ?: json_encode(['success' => false, 'message' => 'Error comunicando con backend']);
