<?php
/**
 * ADMIN API: REFUND SURVEY
 * Admin refunds all participants when survey needs to be cancelled
 * Creator's deposit is forfeited to treasury
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../escrow-system/config/blockchain_config.php';

session_start();
define('ADMIN_API', true);
require_once __DIR__ . '/admin_auth.php';

// Get dynamic survey configuration
require_once __DIR__ . '/../helpers/pricing.php';
$pricing = new PricingHelper();
$survey_config = $pricing->getSurveySettings();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['survey_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'survey_id required']);
    exit;
}

$survey_id = intval($input['survey_id']);

try {
    // Get survey data
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id) as total_responses,
            (SELECT COALESCE(SUM(usa.payment_amount), 0)
             FROM survey_responses sr
             INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
             WHERE sr.survey_id = s.id) as total_pool
        FROM surveys s
        WHERE s.id = ?
    ");
    $stmt->execute([$survey_id]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$survey) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Survey not found']);
        exit;
    }

    // Verify survey is awaiting admin
    if ($survey['status'] !== 'awaiting_admin') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey is not awaiting admin review']);
        exit;
    }

    // Get all participants
    $stmt = $pdo->prepare("
        SELECT
            sr.id,
            sr.user_id,
            sr.wallet_address,
            sr.selected_answer,
            usa.payment_amount
        FROM survey_responses sr
        INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
        WHERE sr.survey_id = ?
    ");
    $stmt->execute([$survey_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($participants)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No participants to refund']);
        exit;
    }

    $total_refund = 0;
    $refunds = [];

    foreach ($participants as $participant) {
        $amount = floatval($participant['payment_amount']);
        $total_refund += $amount;

        $refunds[] = [
            'user_id' => $participant['user_id'],
            'wallet_address' => $participant['wallet_address'],
            'refund_amount' => $amount
        ];
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Update survey status
    $stmt = $pdo->prepare("
        UPDATE surveys
        SET
            status = 'refunded',
            finalized_at = NOW(),
            finalized_by_admin = TRUE,
            admin_id = ?,
            total_refunded = ?,
            deposit_forfeited = TRUE
        WHERE id = ?
    ");
    $stmt->execute([$admin_id, $total_refund, $survey_id]);

    // Process refunds
    foreach ($refunds as $refund) {
        // Record refund
        $stmt = $pdo->prepare("
            INSERT INTO survey_refunds (
                survey_id,
                user_id,
                wallet_address,
                refund_amount,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([
            $survey_id,
            $refund['user_id'],
            $refund['wallet_address'],
            $refund['refund_amount']
        ]);

        // Add to user balance
        if ($refund['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET sphe_balance = sphe_balance + ? WHERE user_id = ?");
            $stmt->execute([$refund['refund_amount'], $refund['user_id']]);
        }

        // Notify user
        if ($refund['user_id']) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        message,
                        created_at
                    ) VALUES (?, 'survey', 'Survey Refunded', ?, NOW())
                ");
                $message = "The survey '{$survey['title']}' has been refunded. {$refund['refund_amount']} SPHE has been added back to your balance.";
                $stmt->execute([$refund['user_id'], $message]);
            } catch (Exception $e) {
                error_log("Notification error: " . $e->getMessage());
            }
        }
    }

    // Deposit goes to treasury (creator forfeited)
    $stmt = $pdo->prepare("
        INSERT INTO treasury_deposits (
            source_type,
            source_id,
            amount,
            reason,
            created_at
        ) VALUES ('survey_refunded', ?, ?, 'Survey refunded by admin - Creator deposit forfeited', NOW())
    ");
    $stmt->execute([$survey_id, $survey['creator_deposit_amount']]);

    // Notify creator
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                message,
                created_at
            ) VALUES (?, 'survey', 'Survey Refunded by Admin', ?, NOW())
        ");
        $deposit_amount = $survey['creator_deposit_amount'];
        $message = "Your survey '{$survey['title']}' was refunded by an admin. All participants received their entry fees back. Your {$deposit_amount} SPHE deposit has been forfeited.";
        $stmt->execute([$survey['creator_id'], $message]);
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'All participants refunded successfully',
        'total_refunded' => count($refunds),
        'total_amount' => $total_refund,
        'deposit_forfeited' => $survey['creator_deposit_amount'],
        'refunds' => $refunds
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Refund survey error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>
