<?php
// ============================================
// CREATE COMMUNITY API
// POST /api/communities/create.php
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

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    // Validate required fields
    $required_fields = ['name', 'description'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit;
        }
    }

    $name = trim($input['name']);
    $description = trim($input['description']);
    $logo_url = isset($input['logo_url']) ? trim($input['logo_url']) : null;
    $banner_url = isset($input['banner_url']) ? trim($input['banner_url']) : null;
    $tx_hash = isset($input['tx_hash']) ? trim($input['tx_hash']) : null;
    $wallet_address = isset($input['wallet_address']) ? trim($input['wallet_address']) : null;

    // Validate name length
    if (strlen($name) < 3 || strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Community name must be between 3 and 100 characters']);
        exit;
    }

    // Validate description length
    if (strlen($description) < 10 || strlen($description) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Description must be between 10 and 500 characters']);
        exit;
    }

    // TODO: Verify SPHE payment (2000 SPHE)
    // For now, we'll skip payment verification for development
    // In production, uncomment this:
    /*
    if (!$tx_hash || !$wallet_address) {
        http_response_code(402);
        echo json_encode(['success' => false, 'message' => 'Payment required']);
        exit;
    }

    require_once __DIR__ . '/../payments/verify_sphe_payment.php';
    $payment_result = verifySphePayment($tx_hash, 2000, env('PLATFORM_WALLET'));

    if (!$payment_result['valid']) {
        http_response_code(402);
        echo json_encode(['success' => false, 'message' => $payment_result['error']]);
        exit;
    }
    */

    // Generate slug
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    $slug = trim($slug, '-');

    // Check if name or slug already exists
    $check_stmt = $pdo->prepare("SELECT id FROM communities WHERE name = ? OR slug = ?");
    $check_stmt->execute([$name, $slug]);

    if ($check_stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Community name already exists']);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert community
        $insert_sql = "
            INSERT INTO communities (name, slug, description, logo_url, banner_url, owner_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";

        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$name, $slug, $description, $logo_url, $banner_url, $user_id]);

        $community_id = $pdo->lastInsertId();

        // Add owner as member
        $member_sql = "
            INSERT INTO community_members (community_id, user_id, role, joined_at)
            VALUES (?, ?, 'owner', NOW())
        ";

        $member_stmt = $pdo->prepare($member_sql);
        $member_stmt->execute([$community_id, $user_id]);

        // Create default "General" group
        $group_sql = "
            INSERT INTO community_groups (community_id, name, description, is_default, access_level, created_by, created_at)
            VALUES (?, 'General', 'Main discussion group', 1, 'all', ?, NOW())
        ";

        $group_stmt = $pdo->prepare($group_sql);
        $group_stmt->execute([$community_id, $user_id]);

        // Record payment (if provided)
        if ($tx_hash && $wallet_address) {
            $payment_sql = "
                INSERT INTO community_payments (community_id, user_id, payment_type, amount, tx_hash, wallet_address, status, created_at)
                VALUES (?, ?, 'create_community', 2000.00, ?, ?, 'confirmed', NOW())
            ";

            $payment_stmt = $pdo->prepare($payment_sql);
            $payment_stmt->execute([$community_id, $user_id, $tx_hash, $wallet_address]);
        }

        // Commit transaction
        $pdo->commit();

        // Get created community data
        $get_sql = "
            SELECT
                c.id,
                c.name,
                c.slug,
                c.description,
                c.logo_url,
                c.banner_url,
                c.owner_id,
                c.member_count,
                c.post_count,
                c.created_at,
                u.username as owner_username
            FROM communities c
            LEFT JOIN users u ON c.owner_id = u.user_id
            WHERE c.id = ?
        ";

        $get_stmt = $pdo->prepare($get_sql);
        $get_stmt->execute([$community_id]);
        $community = $get_stmt->fetch();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Community created successfully',
            'community' => [
                'id' => (int)$community['id'],
                'name' => $community['name'],
                'slug' => $community['slug'],
                'description' => $community['description'],
                'logo_url' => $community['logo_url'],
                'banner_url' => $community['banner_url'],
                'owner_id' => (int)$community['owner_id'],
                'owner_username' => $community['owner_username'],
                'member_count' => (int)$community['member_count'],
                'post_count' => (int)$community['post_count'],
                'created_at' => $community['created_at']
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("ERROR - communities/create.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>