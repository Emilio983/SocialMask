<?php
// ============================================
// JOIN COMMUNITY API
// POST /api/communities/join.php
// ============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../config/connection.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['community_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing community_id']);
        exit;
    }

    $community_id = (int)$input['community_id'];

    // Check if community exists
    $check_sql = "SELECT id, name, is_private, entry_fee_sphe FROM communities WHERE id = ? AND deleted_at IS NULL";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$community_id]);
    $community = $check_stmt->fetch();

    if (!$community) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Community not found']);
        exit;
    }

    // Check if already a member
    $member_check_sql = "SELECT id, is_banned FROM community_members WHERE community_id = ? AND user_id = ?";
    $member_check_stmt = $pdo->prepare($member_check_sql);
    $member_check_stmt->execute([$community_id, $user_id]);
    $existing_member = $member_check_stmt->fetch();

    if ($existing_member) {
        if ($existing_member['is_banned']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are banned from this community']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Already a member of this community'
        ]);
        exit;
    }

    // Check if payment is required for private community
    if ($community['is_private'] && $community['entry_fee_sphe'] > 0) {
        $tx_hash = isset($input['tx_hash']) ? trim($input['tx_hash']) : null;
        $wallet_address = isset($input['wallet_address']) ? trim($input['wallet_address']) : null;

        if (!$tx_hash || !$wallet_address) {
            http_response_code(402);
            echo json_encode([
                'success' => false,
                'message' => 'Payment required',
                'entry_fee' => (float)$community['entry_fee_sphe']
            ]);
            exit;
        }

        // TODO: Verify payment
        // For now, we'll skip verification
        /*
        require_once __DIR__ . '/../payments/verify_sphe_payment.php';
        $payment_result = verifySphePayment($tx_hash, $community['entry_fee_sphe'], env('PLATFORM_WALLET'));

        if (!$payment_result['valid']) {
            http_response_code(402);
            echo json_encode(['success' => false, 'message' => $payment_result['error']]);
            exit;
        }
        */
    }

    // Add member
    $insert_sql = "
        INSERT INTO community_members (community_id, user_id, role, joined_at)
        VALUES (?, ?, 'member', NOW())
    ";

    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([$community_id, $user_id]);

    // Record payment if provided
    if (isset($input['tx_hash']) && isset($input['wallet_address'])) {
        $payment_sql = "
            INSERT INTO community_payments (community_id, user_id, payment_type, amount, tx_hash, wallet_address, status, created_at)
            VALUES (?, ?, 'entry_fee', ?, ?, ?, 'confirmed', NOW())
        ";

        $payment_stmt = $pdo->prepare($payment_sql);
        $payment_stmt->execute([
            $community_id,
            $user_id,
            $community['entry_fee_sphe'],
            $input['tx_hash'],
            $input['wallet_address']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Successfully joined community',
        'community' => [
            'id' => (int)$community['id'],
            'name' => $community['name']
        ],
        'role' => 'member'
    ]);

} catch (Exception $e) {
    error_log("ERROR - communities/join.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>