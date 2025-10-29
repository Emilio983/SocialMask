<?php
// ============================================
// GET CURRENT PLAN - Obtener plan actual del usuario
// ============================================

require_once '../config/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Verificar sesión activa
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    $user_id = $_SESSION['user_id'];

    // Obtener datos del usuario
    $stmt = $pdo->prepare("
        SELECT
            user_id,
            username,
            membership_plan,
            membership_expires,
            wallet_address
        FROM users
        WHERE user_id = ?
    ");

    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }

    $current_plan = $user['membership_plan'] ?? 'free';
    $expires = $user['membership_expires'];

    // Definir precios
    $plan_prices = [
        'free' => 0,
        'platinum' => 100,
        'gold' => 250,
        'diamond' => 500,
        'creator' => 750
    ];

    $plan_names = [
        'free' => 'Plan Gratuito',
        'platinum' => 'Platinum',
        'gold' => 'Gold',
        'diamond' => 'Diamond',
        'creator' => 'Content Creator'
    ];

    $plan_colors = [
        'free' => '#8B949E',
        'platinum' => '#C0C0C0',
        'gold' => '#FFD700',
        'diamond' => '#3B82F6',
        'creator' => '#A855F7'
    ];

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'user' => [
            'user_id' => (int)$user['user_id'],
            'username' => $user['username'],
            'wallet_address' => $user['wallet_address']
        ],
        'plan' => [
            'type' => $current_plan,
            'name' => $plan_names[$current_plan],
            'price' => $plan_prices[$current_plan],
            'color' => $plan_colors[$current_plan],
            'expires' => $expires,
            'is_active' => $expires ? strtotime($expires) > time() : false
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>