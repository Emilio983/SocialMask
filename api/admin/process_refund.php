<?php
/**
 * PROCESS REFUND API
 * Procesa un reembolso manualmente (solo admins)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../../config/connection.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$user_id = isset($input['user_id']) ? intval($input['user_id']) : null;
$amount = isset($input['amount']) ? floatval($input['amount']) : null;
$reason = isset($input['reason']) ? trim($input['reason']) : null;
$refund_type = isset($input['refund_type']) ? $input['refund_type'] : 'manual';

if (!$user_id || !$amount || !$reason) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'user_id, amount and reason are required']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Amount must be positive']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT username, wallet_address FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_user) {
        throw new Exception('User not found');
    }

    // Crear registro de reembolso
    $stmt = $pdo->prepare("
        INSERT INTO refunds (
            user_id,
            refund_type,
            amount,
            reason,
            status,
            requested_by,
            approved_by,
            requested_at,
            processed_at
        ) VALUES (?, ?, ?, ?, 'completed', ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
        $user_id,
        $refund_type,
        $amount,
        $reason,
        $admin_id,
        $admin_id
    ]);

    $refund_id = $pdo->lastInsertId();

    // Actualizar balance del usuario
    $stmt = $pdo->prepare("
        UPDATE users
        SET sphe_balance = sphe_balance + ?
        WHERE user_id = ?
    ");
    $stmt->execute([$amount, $user_id]);

    // Registrar transacción
    $stmt = $pdo->prepare("
        INSERT INTO sphe_transactions (
            from_user_id,
            to_user_id,
            transaction_type,
            amount,
            description,
            reference_type,
            reference_id,
            status,
            created_at
        ) VALUES (NULL, ?, 'survey_refund', ?, ?, 'survey', ?, 'completed', NOW())
    ");

    $description = "Refund: {$reason}";
    $stmt->execute([$user_id, $amount, $description, $refund_id]);

    // Loggear acción de admin
    $stmt = $pdo->prepare("
        INSERT INTO admin_actions (
            admin_id,
            action_type,
            target_type,
            target_id,
            action_description,
            new_value,
            ip_address,
            created_at
        ) VALUES (?, 'force_refund', 'refund', ?, ?, ?, ?, NOW())
    ");

    $action_desc = "Refunded {$amount} SPHE to user {$target_user['username']} - Reason: {$reason}";
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->execute([$admin_id, $refund_id, $action_desc, $amount, $ip]);

    // Crear notificación para el usuario
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            title,
            message,
            created_at
        ) VALUES (?, 'refund', 'Refund Processed', ?, NOW())
    ");

    $notif_msg = "You have received a refund of {$amount} SPHE. Reason: {$reason}";
    $stmt->execute([$user_id, $notif_msg]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Refund processed successfully',
        'refund_id' => $refund_id,
        'user' => $target_user['username'],
        'amount' => $amount,
        'new_balance' => null // Podemos obtenerlo si queremos
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Process refund error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
