<?php
/**
 * ADMIN API: DECLARE SURVEY WINNER
 * Admin declares winner for surveys where creator didn't respond in 48h
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

if (!isset($input['survey_id']) || !isset($input['winning_option'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'survey_id and winning_option required']);
    exit;
}

$survey_id = intval($input['survey_id']);
$winning_option = trim($input['winning_option']);

if (!in_array($winning_option, ['A', 'B'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'winning_option must be A or B']);
    exit;
}

try {
    // Get survey data
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id AND selected_answer = 'A') as votes_a,
            (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id AND selected_answer = 'B') as votes_b,
            (SELECT COALESCE(SUM(usa.payment_amount), 0)
             FROM survey_responses sr
             INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
             WHERE sr.survey_id = s.id AND sr.selected_answer = 'A') as pool_a,
            (SELECT COALESCE(SUM(usa.payment_amount), 0)
             FROM survey_responses sr
             INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
             WHERE sr.survey_id = s.id AND sr.selected_answer = 'B') as pool_b
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

    // Calculate distributions
    $pool_a = floatval($survey['pool_a']);
    $pool_b = floatval($survey['pool_b']);
    $total_pool = $pool_a + $pool_b;

    $winning_pool = $winning_option === 'A' ? $pool_a : $pool_b;
    $losing_pool = $winning_option === 'A' ? $pool_b : $pool_a;

    // NO commission for creator (forfeited deposit)
    // 100% of losing pool goes to winners
    $winners_share = $losing_pool;
    $total_for_distribution = $winning_pool + $winners_share;

    // Get winners
    $stmt = $pdo->prepare("
        SELECT
            sr.id,
            sr.user_id,
            sr.wallet_address,
            usa.payment_amount
        FROM survey_responses sr
        INNER JOIN user_survey_access usa ON sr.payment_id = usa.payment_id
        WHERE sr.survey_id = ? AND sr.selected_answer = ?
    ");
    $stmt->execute([$survey_id, $winning_option]);
    $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($winners)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No winners found for this option']);
        exit;
    }

    $total_winner_bets = array_sum(array_column($winners, 'payment_amount'));

    // Calculate payouts
    $payouts = [];
    foreach ($winners as $winner) {
        $bet_amount = floatval($winner['payment_amount']);
        $proportion = $total_winner_bets > 0 ? ($bet_amount / $total_winner_bets) : 0;
        $payout_amount = $bet_amount + ($winners_share * $proportion);

        $payouts[] = [
            'user_id' => $winner['user_id'],
            'wallet_address' => $winner['wallet_address'],
            'bet_amount' => $bet_amount,
            'payout_amount' => $payout_amount,
            'profit' => $payout_amount - $bet_amount
        ];
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Update survey
    $stmt = $pdo->prepare("
        UPDATE surveys
        SET
            status = 'finalized',
            winning_option = ?,
            finalized_at = NOW(),
            finalized_by_admin = TRUE,
            admin_id = ?,
            creator_commission = 0,
            deposit_refunded = 0,
            deposit_forfeited = TRUE,
            responded_within_deadline = FALSE
        WHERE id = ?
    ");
    $stmt->execute([$winning_option, $admin_id, $survey_id]);

    // Record payouts
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

        // Add to user balance
        if ($payout['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET sphe_balance = sphe_balance + ? WHERE user_id = ?");
            $stmt->execute([$payout['payout_amount'], $payout['user_id']]);
        }
    }

    // Deposit goes to treasury
    $stmt = $pdo->prepare("
        INSERT INTO treasury_deposits (
            source_type,
            source_id,
            amount,
            reason,
            created_at
        ) VALUES ('survey_forfeited_deposit', ?, ?, 'Creator did not respond within 48h - Admin finalized', NOW())
    ");
    $stmt->execute([$survey_id, $survey['creator_deposit_amount']]);

    // Notify winners
    foreach ($winners as $winner) {
        if ($winner['user_id']) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        message,
                        created_at
                    ) VALUES (?, 'survey', 'You Won!', ?, NOW())
                ");
                $message = "Congratulations! You won the survey '{$survey['title']}'. Your winnings have been added to your balance.";
                $stmt->execute([$winner['user_id'], $message]);
            } catch (Exception $e) {
                error_log("Notification error: " . $e->getMessage());
            }
        }
    }

    // Notify creator about forfeiture
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                message,
                created_at
            ) VALUES (?, 'survey', 'Survey Finalized by Admin', ?, NOW())
        ");
        $deposit_amount = $survey['creator_deposit_amount'];
        $message = "Your survey '{$survey['title']}' was finalized by an admin because you did not respond within 48 hours. Your {$deposit_amount} SPHE deposit has been forfeited.";
        $stmt->execute([$survey['creator_id'], $message]);
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Winner declared successfully by admin',
        'winning_option' => $winning_option,
        'total_winners' => count($winners),
        'total_distributed' => $total_for_distribution,
        'deposit_forfeited' => $survey['creator_deposit_amount'],
        'payouts' => $payouts
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Admin declare winner error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>
