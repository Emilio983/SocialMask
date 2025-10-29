<?php
/**
 * DECLARE WINNER API
 * Permite al creador declarar el ganador de una encuesta cerrada
 * Debe hacerse dentro de 48 horas del cierre para recibir comisión + depósito
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

// Get dynamic survey configuration
require_once __DIR__ . '/../helpers/pricing.php';
$pricing = new PricingHelper();
$survey_config = $pricing->getSurveySettings();

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['survey_id']) || !isset($input['winning_option'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'survey_id and winning_option are required']);
    exit;
}

$survey_id = intval($input['survey_id']);
$winning_option = trim($input['winning_option']); // 'A' or 'B'

if (!in_array($winning_option, ['A', 'B'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'winning_option must be A or B']);
    exit;
}

try {
    // 1. Get survey information
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id AND selected_answer = 'A') as votes_a,
            (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id AND selected_answer = 'B') as votes_b,
            (SELECT SUM(sr.payment_amount) FROM survey_responses sr
             INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
             WHERE sr.survey_id = s.id AND sr.selected_answer = 'A') as total_amount_a,
            (SELECT SUM(sr.payment_amount) FROM survey_responses sr
             INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
             WHERE sr.survey_id = s.id AND sr.selected_answer = 'B') as total_amount_b
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

    // 2. Verify user is the creator
    if ($survey['creator_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only the survey creator can declare the winner']);
        exit;
    }

    // 3. Verify survey is closed
    if ($survey['status'] !== 'closed' && strtotime($survey['close_date']) > time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey is not closed yet']);
        exit;
    }

    // 4. Verify survey hasn't been finalized
    if ($survey['status'] === 'finalized') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Survey has already been finalized']);
        exit;
    }

    // 5. Check if within 48 hours of closing
    $close_time = strtotime($survey['close_date']);
    $current_time = time();
    $hours_since_close = ($current_time - $close_time) / 3600;
    $within_deadline = $hours_since_close <= 48;

    // 6. Calculate distributions
    $total_pool = floatval($survey['total_amount_a']) + floatval($survey['total_amount_b']);
    $winning_amount = $winning_option === 'A' ? floatval($survey['total_amount_a']) : floatval($survey['total_amount_b']);
    $losing_amount = $winning_option === 'A' ? floatval($survey['total_amount_b']) : floatval($survey['total_amount_a']);

    // Commission: Dynamic percentage from settings
    $commission_rate = $survey_config['creator_commission'] / 100;
    $creator_commission = $within_deadline ? ($losing_amount * $commission_rate) : 0;

    // Deposit refund: only if within 48h
    $deposit_refund = $within_deadline ? floatval($survey['creator_deposit_amount']) : 0;

    // Winners share: losing amount - commission
    $winners_pool = $losing_amount - $creator_commission;

    // Total for winners: their original bets + winners pool
    $total_for_distribution = $winning_amount + $winners_pool;

    // 7. Get all winners
    $stmt = $pdo->prepare("
        SELECT
            sr.id,
            sr.user_id,
            sr.wallet_address,
            usa.payment_amount,
            usa.blockchain_tx_hash
        FROM survey_responses sr
        INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
        WHERE sr.survey_id = ? AND sr.selected_answer = ?
    ");
    $stmt->execute([$survey_id, $winning_option]);
    $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_winner_bets = array_sum(array_column($winners, 'payment_amount'));

    // Calculate each winner's share (proportional to their bet)
    $payouts = [];
    foreach ($winners as $winner) {
        $bet_amount = floatval($winner['payment_amount']);
        $proportion = $total_winner_bets > 0 ? ($bet_amount / $total_winner_bets) : 0;
        $payout_amount = $bet_amount + ($winners_pool * $proportion);

        $payouts[] = [
            'user_id' => $winner['user_id'],
            'wallet_address' => $winner['wallet_address'],
            'bet_amount' => $bet_amount,
            'payout_amount' => $payout_amount,
            'profit' => $payout_amount - $bet_amount
        ];
    }

    // 8. Begin transaction
    $pdo->beginTransaction();

    // 9. Update survey status
    $stmt = $pdo->prepare("
        UPDATE surveys
        SET
            status = 'finalized',
            winning_option = ?,
            finalized_at = NOW(),
            finalized_by_creator = ?,
            creator_commission = ?,
            deposit_refunded = ?,
            responded_within_deadline = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $winning_option,
        $within_deadline ? 1 : 0,
        $creator_commission,
        $deposit_refund,
        $within_deadline ? 1 : 0,
        $survey_id
    ]);

    // 10. Record payouts for winners
    foreach ($payouts as $payout) {
        $stmt = $pdo->prepare("
            INSERT INTO survey_payouts (
                survey_id,
                user_id,
                wallet_address,
                payout_amount,
                bet_amount,
                profit_amount,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $survey_id,
            $payout['user_id'],
            $payout['wallet_address'],
            $payout['payout_amount'],
            $payout['bet_amount'],
            $payout['profit']
        ]);
    }

    // 11. Record creator commission
    if ($creator_commission > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO survey_creator_earnings (
                survey_id,
                creator_id,
                commission_amount,
                deposit_refund,
                total_earned,
                earned_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $survey_id,
            $user_id,
            $creator_commission,
            $deposit_refund,
            $creator_commission + $deposit_refund
        ]);

        // Add to creator's SPHE balance
        $stmt = $pdo->prepare("UPDATE users SET sphe_balance = sphe_balance + ? WHERE user_id = ?");
        $stmt->execute([$creator_commission + $deposit_refund, $user_id]);
    }

    // 12. If late (>48h), deposit goes to treasury
    if (!$within_deadline) {
        $stmt = $pdo->prepare("
            INSERT INTO treasury_deposits (
                source_type,
                source_id,
                amount,
                reason,
                created_at
            ) VALUES ('survey_late_response', ?, ?, 'Creator did not respond within 48 hours', NOW())
        ");
        $stmt->execute([$survey_id, $survey['creator_deposit_amount']]);
    }

    // 13. Create notifications for winners
    foreach ($winners as $winner) {
        if ($winner['user_id']) {
            try {
                $stmt_notif = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        message,
                        created_at
                    ) VALUES (?, 'survey', 'You Won!', ?, NOW())
                ");
                $message = "Congratulations! You won the survey '{$survey['title']}'. Your winnings will be distributed soon.";
                $stmt_notif->execute([$winner['user_id'], $message]);
            } catch (Exception $e) {
                error_log("Error creating notification: " . $e->getMessage());
            }
        }
    }

    // 14. Create notification for creator
    try {
        $stmt_notif = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                message,
                created_at
            ) VALUES (?, 'survey', ?, ?, NOW())
        ");

        if ($within_deadline) {
            $title = "Survey Finalized Successfully";
            $message = "Your survey has been finalized! You earned {$creator_commission} SPHE commission + {$deposit_refund} SPHE deposit refund.";
        } else {
            $title = "Survey Finalized (Late)";
            $message = "Your survey has been finalized, but you responded after 48 hours. Your deposit has been forfeited.";
        }

        $stmt_notif->execute([$user_id, $title, $message]);
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Winner declared successfully',
        'within_deadline' => $within_deadline,
        'winning_option' => $winning_option,
        'total_winners' => count($winners),
        'creator_commission' => $creator_commission,
        'deposit_refunded' => $deposit_refund,
        'total_earned' => $creator_commission + $deposit_refund,
        'payouts' => $payouts
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Declare winner error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
