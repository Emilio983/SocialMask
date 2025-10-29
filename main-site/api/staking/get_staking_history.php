<?php
/**
 * API: Obtener historial de staking
 * Endpoint: /api/staking/get_staking_history.php
 * Método: GET
 * Descripción: Retorna historial completo de transacciones de staking
 */

require_once '../../config/config.php';
require_once '../cors_helper.php';
require_once '../response_helper.php';
require_once '../error_handler.php';

header('Content-Type: application/json');

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    // Obtener parámetros
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, stake, unstake, claim
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $pool_id = isset($_GET['pool_id']) ? (int)$_GET['pool_id'] : null;

    if ($user_id <= 0) {
        throw new Exception('ID de usuario inválido');
    }

    // Validar límite
    if ($limit > 100) {
        $limit = 100;
    }

    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Usuario no encontrado');
    }

    // Construir query según el tipo
    $where_clauses = ["user_id = ?"];
    $params = [$user_id];

    if ($type !== 'all') {
        $valid_types = ['stake', 'unstake', 'claim', 'emergency_withdraw'];
        if (!in_array($type, $valid_types)) {
            throw new Exception('Tipo de transacción inválido');
        }
        $where_clauses[] = "transaction_type = ?";
        $params[] = $type;
    }

    if ($pool_id !== null) {
        $where_clauses[] = "pool_id = ?";
        $params[] = $pool_id;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Obtener total de registros
    $count_sql = "SELECT COUNT(*) as total FROM staking_transactions_log WHERE $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Obtener transacciones
    $params_with_pagination = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.transaction_type,
            t.amount,
            t.pool_id,
            p.name as pool_name,
            t.tx_hash,
            t.gas_used,
            t.status,
            t.error_message,
            t.created_at,
            t.confirmed_at,
            CASE 
                WHEN t.transaction_type = 'stake' THEN 'Depósito de Staking'
                WHEN t.transaction_type = 'unstake' THEN 'Retiro de Staking'
                WHEN t.transaction_type = 'claim' THEN 'Reclamación de Rewards'
                WHEN t.transaction_type = 'emergency_withdraw' THEN 'Retiro de Emergencia'
            END as type_label
        FROM staking_transactions_log t
        LEFT JOIN staking_pools_info p ON t.pool_id = p.pool_id
        WHERE $where_sql
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params_with_pagination);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estadísticas del período
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM staking_transactions_log
        WHERE user_id = ?
        GROUP BY transaction_type
    ");
    $stmt->execute([$user_id]);
    $stats_by_type = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats_by_type[$row['transaction_type']] = [
            'count' => (int)$row['count'],
            'total_amount' => $row['total_amount']
        ];
    }

    // Obtener actividad reciente (últimos 7 días)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            transaction_type,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM staking_transactions_log
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at), transaction_type
        ORDER BY date DESC
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $transactions,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
                'current_page' => floor($offset / $limit) + 1,
                'total_pages' => ceil($total / $limit)
            ],
            'stats_by_type' => $stats_by_type,
            'recent_activity' => $recent_activity
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en get_staking_history.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
