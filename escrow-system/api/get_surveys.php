<?php
/**
 * GET SURVEYS API
 * Obtiene lista de encuestas activas
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Incluir configuración
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../config/blockchain_config.php';

$status = isset($_GET['status']) ? $_GET['status'] : 'active';
$limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 100) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

try {
    // Query base
    $where_conditions = [];
    $params = [];

    if ($status !== 'all') {
        $where_conditions[] = "s.status = :status";
        $params[':status'] = $status;
    }

    $where_clause = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Obtener encuestas
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.title,
            s.description,
            s.price,
            s.status,
            s.close_date,
            s.max_participants,
            s.total_prize_pool,
            s.created_at,
            u.username as creator_username,
            u.wallet_address as creator_wallet,
            COUNT(DISTINCT p.from_address) as participant_count,
            SUM(CASE WHEN p.confirmed = TRUE THEN p.amount ELSE 0 END) as confirmed_pool
        FROM surveys s
        LEFT JOIN users u ON s.created_by = u.user_id
        LEFT JOIN payments p ON s.id = p.survey_id
        {$where_clause}
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear datos
    foreach ($surveys as &$survey) {
        $survey['is_open'] = (strtotime($survey['close_date']) > time() && $survey['status'] === 'active');
        $survey['is_full'] = false;

        if ($survey['max_participants']) {
            $survey['is_full'] = ($survey['participant_count'] >= $survey['max_participants']);
        }

        // Convertir Wei a SPHE para el frontend
        $survey['confirmed_pool_sphe'] = weiToSphe($survey['confirmed_pool']);
    }

    echo json_encode([
        'success' => true,
        'surveys' => $surveys,
        'count' => count($surveys),
        'limit' => $limit,
        'offset' => $offset
    ]);

} catch (PDOException $e) {
    error_log("Get surveys error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch surveys'
    ]);
}
