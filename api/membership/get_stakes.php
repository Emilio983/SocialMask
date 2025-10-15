<?php
// ============================================
// GET STAKES - Obtener stakes de un usuario
// ============================================

require_once __DIR__ . '/../../config/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }

    // Verificar sesión activa
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    $user_id = $_SESSION['user_id'];

    // Obtener todos los stakes del usuario
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.plan_type,
            s.staked_amount,
            s.unlock_date,
            s.claimed,
            s.blockchain_stake_tx,
            s.blockchain_claim_tx,
            s.stake_id_on_contract,
            s.created_at,
            s.claimed_at,
            mt.blockchain_tx_hash as purchase_tx_hash
        FROM membership_stakes s
        LEFT JOIN membership_transactions mt ON s.transaction_id = mt.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");

    $stmt->execute([$user_id]);
    $stakes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estadísticas
    $total_staked = 0;
    $total_claimed = 0;
    $active_stakes = 0;
    $unlocked_stakes = 0;
    $now = time();

    foreach ($stakes as &$stake) {
        // Calcular si está desbloqueado
        $unlock_time = strtotime($stake['unlock_date']);
        $stake['is_unlocked'] = ($now >= $unlock_time && !$stake['claimed']);
        $stake['days_until_unlock'] = max(0, ceil(($unlock_time - $now) / 86400));

        // Estadísticas
        if (!$stake['claimed']) {
            $total_staked += $stake['staked_amount'];
            $active_stakes++;

            if ($stake['is_unlocked']) {
                $unlocked_stakes++;
            }
        } else {
            $total_claimed += $stake['staked_amount'];
        }

        // Convertir booleanos a bool para JSON
        $stake['claimed'] = (bool)$stake['claimed'];
        $stake['is_unlocked'] = (bool)$stake['is_unlocked'];
    }

    // Respuesta
    echo json_encode([
        'success' => true,
        'data' => [
            'stakes' => $stakes,
            'summary' => [
                'total_stakes' => count($stakes),
                'active_stakes' => $active_stakes,
                'unlocked_stakes' => $unlocked_stakes,
                'total_staked' => $total_staked,
                'total_claimed' => $total_claimed,
                'total_pending' => $total_staked
            ]
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
