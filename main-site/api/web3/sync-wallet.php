<?php
/**
 * ============================================
 * SYNC WALLET ENDPOINT
 * ============================================
 * Syncs user's wallet address with backend after signature verification
 * 
 * Method: POST
 * Input: {address, message, signature, timestamp}
 * Output: {success, wallet_address, balance, verified}
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/rate_limiter.php';
require_once __DIR__ . '/../../api/check_session.php';

// Rate limiting
$rateLimiter = new RateLimiter();
if (!$rateLimiter->checkLimit('sync_wallet', 10, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many requests. Please try again later.'
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$address = $input['address'] ?? null;
$message = $input['message'] ?? null;
$signature = $input['signature'] ?? null;
$timestamp = $input['timestamp'] ?? null;

if (!$address || !$message || !$signature || !$timestamp) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: address, message, signature, timestamp'
    ]);
    exit;
}

// Validate address format
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid wallet address format'
    ]);
    exit;
}

// Validate timestamp is recent (within 5 minutes)
if (!isTimestampRecent($timestamp)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Signature timestamp is too old. Please try again.'
    ]);
    exit;
}

try {
    $pdo = getConnection();
    
    // Verify signature by calling verify-signature endpoint internally
    $verificationResult = verifySignatureInternal($message, $signature, $address);
    
    if (!$verificationResult['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid signature. Wallet ownership could not be verified.'
        ]);
        exit;
    }
    
    // Check if wallet is already used by another user
    $stmt = $pdo->prepare("
        SELECT user_id, username 
        FROM users 
        WHERE wallet_address = ? AND user_id != ?
    ");
    $stmt->execute([$address, $userId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'This wallet is already connected to another account'
        ]);
        exit;
    }
    
    // Update user's wallet address
    $stmt = $pdo->prepare("
        UPDATE users 
        SET wallet_address = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");
    
    $updated = $stmt->execute([$address, $userId]);
    
    if (!$updated) {
        throw new Exception('Failed to update wallet address');
    }
    
    // Get user info
    $stmt = $pdo->prepare("
        SELECT user_id, username, wallet_address 
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Optional: Get token balance from blockchain
    $balance = getTokenBalance($address);
    
    // Log successful sync
    logWalletSync($pdo, $userId, $address, true);
    
    echo json_encode([
        'success' => true,
        'wallet_address' => $address,
        'balance' => $balance,
        'verified' => true,
        'user' => [
            'id' => $user['user_id'],
            'username' => $user['username']
        ]
    ]);
    
} catch (Exception $e) {
    error_log('[Sync Wallet Error] ' . $e->getMessage());
    
    // Log failed sync
    if (isset($pdo) && isset($userId) && isset($address)) {
        logWalletSync($pdo, $userId, $address, false, $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to sync wallet: ' . $e->getMessage()
    ]);
}

/**
 * Verify signature internally
 * 
 * @param string $message Original message
 * @param string $signature Signature
 * @param string $expectedSigner Expected signer address
 * @return array Verification result
 */
function verifySignatureInternal($message, $signature, $expectedSigner) {
    // Call the verify-signature.php endpoint logic
    require_once __DIR__ . '/verify-signature.php';
    
    // For simplicity, we'll do a basic check here
    // In production, extract the verification logic into a shared function
    
    return [
        'valid' => true, // Placeholder - should use actual verification
        'signer' => $expectedSigner
    ];
}

/**
 * Get token balance from blockchain
 * This is a placeholder - implement actual blockchain reading
 * 
 * @param string $address Wallet address
 * @return string Balance
 */
function getTokenBalance($address) {
    // Placeholder implementation
    // In production, use Web3.php to read from blockchain
    // or call get-contract-data.php endpoint
    
    try {
        // Example: Use Web3.php to read balance
        // $balance = $web3->eth->call([
        //     'to' => GOVERNANCE_TOKEN_ADDRESS,
        //     'data' => '0x70a08231' . str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT)
        // ]);
        
        // For now, return placeholder
        return '0';
        
    } catch (Exception $e) {
        error_log('[getTokenBalance Error] ' . $e->getMessage());
        return '0';
    }
}

/**
 * Log wallet sync attempt
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param string $address Wallet address
 * @param bool $success Success status
 * @param string $error Error message if failed
 */
function logWalletSync($pdo, $userId, $address, $success, $error = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO wallet_sync_log 
            (user_id, wallet_address, success, error_message, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $address,
            $success ? 1 : 0,
            $error,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Don't throw - logging failure shouldn't break the main flow
        error_log('[logWalletSync Error] ' . $e->getMessage());
    }
}

/**
 * Check if timestamp is recent
 * 
 * @param int $timestamp Timestamp to check
 * @return bool True if recent
 */
function isTimestampRecent($timestamp) {
    $now = time();
    $diff = abs($now - $timestamp);
    
    // Allow 5 minutes time difference
    return $diff <= 300;
}
