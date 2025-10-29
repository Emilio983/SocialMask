<?php
/**
 * CREATE SURVEY API
 * Handles creation of crypto surveys with SPHE deposit requirement
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

// Rate limiting - Max 5 survey creations per hour per user
require_once __DIR__ . '/../helpers/rate_limiter.php';
$rate_limit_identifier = $_SERVER['REMOTE_ADDR'] . '_survey_create';
if (!checkRateLimit($rate_limit_identifier, 5, 3600)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many surveys created. Maximum 5 surveys per hour.'
    ]);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Start session to get user info
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['title', 'entry_price', 'option_a', 'option_b', 'duration_hours', 'deposit_tx_hash', 'wallet_address'];
foreach ($required as $field) {
    if (!isset($input[$field]) || trim($input[$field]) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Field '{$field}' is required"]);
        exit;
    }
}

$title = trim($input['title']);
$description = isset($input['description']) ? trim($input['description']) : null;
$entry_price = floatval($input['entry_price']);
$option_a = trim($input['option_a']);
$option_b = trim($input['option_b']);
$duration_hours = intval($input['duration_hours']);
$deposit_tx_hash = strtolower(trim($input['deposit_tx_hash']));
$wallet_address = strtolower(trim($input['wallet_address']));
$community_id = isset($input['community_id']) ? intval($input['community_id']) : null;
$group_id = isset($input['group_id']) ? intval($input['group_id']) : null;

// Obtener configuración dinámica de surveys
require_once __DIR__ . '/../helpers/pricing.php';
$pricing = new PricingHelper();
$survey_config = $pricing->getSurveySettings();

// Validate entry price usando configuración dinámica
if ($entry_price < $survey_config['min_entry_price']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => "Entry price must be at least {$survey_config['min_entry_price']} SPHE"
    ]);
    exit;
}

// Validate wallet format
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid wallet address']);
    exit;
}

// Validate tx_hash format
if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $deposit_tx_hash)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid transaction hash']);
    exit;
}

try {
    // 1. Verify user has required membership plan
    $stmt = $pdo->prepare("SELECT membership_plan FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $allowed_plans = ['diamond', 'gold', 'creator'];
    if (!in_array($user['membership_plan'], $allowed_plans)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'You need Diamond, Gold, or Creator membership to create surveys',
            'current_plan' => $user['membership_plan']
        ]);
        exit;
    }

    // 2. Verify deposit transaction hasn't been used before
    $stmt = $pdo->prepare("
        SELECT id FROM surveys
        WHERE creator_deposit_tx_hash = ?
    ");
    $stmt->execute([$deposit_tx_hash]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'This transaction has already been used']);
        exit;
    }

    // 3. Verify transaction on blockchain using Infura
    require_once __DIR__ . '/../../escrow-system/config/blockchain_config.php';
    $infura_url = POLYGON_MAINNET_RPC;

    $tx_request = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'eth_getTransactionByHash',
        'params' => [$deposit_tx_hash],
        'id' => 1
    ]);

    $ch = curl_init($infura_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $tx_request);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $tx_response = curl_exec($ch);
    curl_close($ch);

    $tx_data = json_decode($tx_response, true);

    if (!isset($tx_data['result']) || !$tx_data['result']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction not found on blockchain']);
        exit;
    }

    $tx = $tx_data['result'];

    // Verify transaction is from user's wallet
    if (strtolower($tx['from']) !== $wallet_address) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction is not from your wallet']);
        exit;
    }

    // Verify transaction is to SPHE contract (ERC-20 transfer)
    $sphe_contract = '0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b';
    if (strtolower($tx['to']) !== strtolower($sphe_contract)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction must be SPHE token transfer']);
        exit;
    }

    // Verify transaction has been mined (has blockNumber)
    if (!isset($tx['blockNumber']) || $tx['blockNumber'] === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction not yet confirmed']);
        exit;
    }

    // ⚠️ CRITICAL: Verify amount in ERC-20 transaction
    // For ERC-20, the amount is encoded in the 'input' field
    $input = $tx['input'];

    // Verify it's a transfer function call
    // transfer(address,uint256) = 0xa9059cbb
    if (substr($input, 0, 10) !== '0xa9059cbb') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Transaction must be a transfer call']);
        exit;
    }

    // Extract amount from input data
    // Input structure: 0xa9059cbb (method) + 64 chars (to address) + 64 chars (amount)
    if (strlen($input) < 138) { // 10 (0xa9059cbb) + 64 + 64
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid transaction data']);
        exit;
    }

    // Extract amount hex (last 64 characters)
    $amount_hex = substr($input, 74, 64);

    // Convert to decimal using BC Math for large numbers
    $amount_wei = '0';
    for ($i = 0; $i < strlen($amount_hex); $i++) {
        $amount_wei = bcmul($amount_wei, '16');
        $amount_wei = bcadd($amount_wei, (string)hexdec($amount_hex[$i]));
    }

    // Expected amount from config (default 1000 SPHE)
    $required_deposit = $survey_config['creator_deposit'];
    $expected_amount_wei = strval($required_deposit) . str_repeat('0', 18);

    // Verify amount (must be at least 1000 SPHE)
    if (bccomp($amount_wei, $expected_amount_wei) < 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient deposit amount. Required: 1000 SPHE, sent: ' . bcdiv($amount_wei, bcpow('10', '18'), 2) . ' SPHE'
        ]);
        exit;
    }

    // 4. Calculate close date
    $close_date = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));

    // 5. Insert survey into database
    $stmt = $pdo->prepare("
        INSERT INTO surveys (
            creator_id,
            community_id,
            group_id,
            title,
            description,
            entry_price_sphe,
            close_date,
            status,
            creator_deposit_amount,
            creator_deposit_tx_hash,
            creator_deposit_wallet,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $user_id,
        $community_id,
        $group_id,
        $title,
        $description,
        $entry_price,
        $close_date,
        $required_deposit,
        $deposit_tx_hash,
        $wallet_address
    ]);

    $survey_id = $pdo->lastInsertId();

    // 6. Insert survey questions (Option A and Option B)
    $stmt = $pdo->prepare("
        INSERT INTO survey_questions (
            survey_id,
            question_text,
            question_type,
            question_order
        ) VALUES (?, ?, 'betting_option', ?)
    ");

    // Insert Option A
    $stmt->execute([$survey_id, $option_a, 1]);

    // Insert Option B
    $stmt->execute([$survey_id, $option_b, 2]);

    // 7. Create notification for creator
    try {
        $stmt_notif = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                title,
                message,
                created_at
            ) VALUES (?, 'survey', 'Survey Created', ?, NOW())
        ");

        $message = "Your survey '{$title}' has been created successfully! Your {$required_deposit} SPHE deposit will be refunded when you declare the winner within 48 hours after closing. You'll also earn {$survey_config['creator_commission']}% commission!";
        $stmt_notif->execute([$user_id, $message]);
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'survey_id' => $survey_id,
        'message' => 'Survey created successfully',
        'close_date' => $close_date,
        'deposit_confirmed' => true
    ]);

} catch (PDOException $e) {
    error_log("Create survey error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
