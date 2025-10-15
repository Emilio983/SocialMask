<?php

/**
 * Recover public key from ECDSA signature
 * NOTE: This is a placeholder - implement with proper crypto library in production
 * For production use: composer require kornrunner/keccak or web3p/ethereum-util
 */
function recoverPublicKey($message_hash, $r, $s, $recovery_id) {
    // Placeholder implementation
    // Real implementation requires elliptic curve cryptography library
    error_log("WARNING - recoverPublicKey: Using placeholder implementation. Implement proper ECDSA recovery for production.");
    return null;
}

/**
 * Convert public key to Ethereum address
 * NOTE: This is a placeholder - implement with proper library in production
 */
function publicKeyToAddress($public_key) {
    // Placeholder implementation
    error_log("WARNING - publicKeyToAddress: Using placeholder implementation. Implement proper address derivation for production.");
    return '0x0000000000000000000000000000000000000000';
}

/**
 * Verify Ethereum signature (Development mode)
 * NOTE: This is a basic implementation for development purposes
 * For production, use a proper Ethereum signature verification library
 * Recommended: web3.php or ethereum-php libraries
 */
function verifySignature($message, $signature, $wallet_address) {
    // Basic parameter validation
    if (empty($message) || empty($signature) || empty($wallet_address)) {
        error_log("ERROR - Signature verification: empty parameters");
        return false;
    }

    // Remove 0x prefix from signature if present
    $clean_signature = str_replace('0x', '', $signature);

    // Validate signature format - should be 130 hex characters (65 bytes: r + s + v)
    if (!preg_match('/^[a-fA-F0-9]{130}$/', $clean_signature)) {
        error_log("ERROR - Signature verification: invalid signature format. Length: " . strlen($clean_signature));
        return false;
    }

    // Validate wallet address format
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
        error_log("ERROR - Signature verification: invalid wallet address format");
        return false;
    }

    // Validate message format contains expected text
    if (strpos($message, 'Registrarse en TheSocialMask') === false &&
        strpos($message, 'Inicia sesión en TheSocialMask') === false &&
        strpos($message, 'Sign in to TheSocialMask') === false) {
        error_log("ERROR - Signature verification: invalid message format - missing TheSocialMask text");
        return false;
    }

    // Validate message contains nonce
    if (strpos($message, 'Nonce:') === false && strpos($message, 'nonce:') === false) {
        error_log("ERROR - Signature verification: invalid message format - missing nonce");
        return false;
    }

    // ⚠️ WARNING: This is a simplified verification for DEVELOPMENT ONLY
    // In production, implement proper ECDSA signature recovery and verification
    // TODO: Implement proper cryptographic verification using a library

    if (DEBUG) {
        error_log("INFO - Signature verification passed (development mode) for wallet: " . $wallet_address);
    }

    return true;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) &&
           isset($_SESSION['wallet_address']) &&
           isset($_SESSION['login_time']) &&
           (time() - $_SESSION['login_time']) < 86400; // 24 hours
}

/**
 * Get current user data
 */
function getCurrentUser($pdo) {
    if (!isAuthenticated()) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT user_id, username, wallet_address, wallet_type, sphe_balance, created_at, last_login
            FROM users
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error in getCurrentUser: " . $e->getMessage());
        return null;
    }
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate secure random string
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Validate Ethereum address
 */
function isValidEthereumAddress($address) {
    return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}

/**
 * Format SPHE balance for display
 */
function formatSpheBalance($balance) {
    return number_format($balance, 8, '.', ',');
}

/**
 * Log user activity
 */
function logActivity($pdo, $user_id, $action, $details = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_activity_logs (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

?>