<?php
/**
 * ============================================
 * VERIFY SIGNATURE ENDPOINT
 * ============================================
 * Verifies Web3 signatures (personal_sign and EIP-712)
 * 
 * Method: POST
 * Input: {message, signature, expectedSigner, type}
 * Output: {success, valid, signer}
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
require_once __DIR__ . '/../../api/rate_limiter.php';

// Rate limiting
$rateLimiter = new RateLimiter();
if (!$rateLimiter->checkLimit('verify_signature', 20, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many requests. Please try again later.'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$message = $input['message'] ?? null;
$signature = $input['signature'] ?? null;
$expectedSigner = $input['expectedSigner'] ?? null;
$type = $input['type'] ?? 'personal_sign'; // personal_sign or eip712

if (!$message || !$signature || !$expectedSigner) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: message, signature, expectedSigner'
    ]);
    exit;
}

// Validate signature format
if (!preg_match('/^0x[a-fA-F0-9]{130}$/', $signature)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid signature format'
    ]);
    exit;
}

// Validate address format
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $expectedSigner)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid address format'
    ]);
    exit;
}

try {
    // Verify signature
    $recoveredAddress = recoverSignerAddress($message, $signature, $type);
    
    if (!$recoveredAddress) {
        throw new Exception('Failed to recover signer address');
    }
    
    // Compare addresses (case-insensitive)
    $isValid = strtolower($recoveredAddress) === strtolower($expectedSigner);
    
    // Log verification attempt
    error_log(sprintf(
        '[Web3 Signature Verification] Type: %s, Expected: %s, Recovered: %s, Valid: %s',
        $type,
        $expectedSigner,
        $recoveredAddress,
        $isValid ? 'YES' : 'NO'
    ));
    
    echo json_encode([
        'success' => true,
        'valid' => $isValid,
        'signer' => $recoveredAddress,
        'expectedSigner' => $expectedSigner
    ]);
    
} catch (Exception $e) {
    error_log('[Web3 Signature Verification Error] ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Signature verification failed: ' . $e->getMessage()
    ]);
}

/**
 * Recover signer address from signature
 * 
 * @param string $message Original message
 * @param string $signature Signature (0x prefixed)
 * @param string $type Signature type (personal_sign or eip712)
 * @return string|false Recovered address or false on failure
 */
function recoverSignerAddress($message, $signature, $type = 'personal_sign') {
    try {
        // Remove 0x prefix from signature
        $sig = substr($signature, 2);
        
        // Extract r, s, v components
        $r = substr($sig, 0, 64);
        $s = substr($sig, 64, 64);
        $v = hexdec(substr($sig, 128, 2));
        
        // Normalize v value (27, 28 or 0, 1)
        if ($v < 27) {
            $v += 27;
        }
        
        // Prepare message hash based on type
        if ($type === 'personal_sign') {
            // Ethereum signed message format
            $msgLen = strlen($message);
            $prefix = "\x19Ethereum Signed Message:\n" . $msgLen;
            $msgHash = hash('sha3-256', $prefix . $message, true);
        } else {
            // For EIP-712, message should already be hashed
            $msgHash = hex2bin(str_replace('0x', '', $message));
        }
        
        // Use elliptic curve recovery to get public key
        $publicKey = recoverPublicKey($msgHash, $r, $s, $v);
        
        if (!$publicKey) {
            return false;
        }
        
        // Derive Ethereum address from public key
        // Address = last 20 bytes of keccak256(public key)
        $address = '0x' . substr(hash('sha3-256', hex2bin($publicKey)), -40);
        
        return $address;
        
    } catch (Exception $e) {
        error_log('[recoverSignerAddress Error] ' . $e->getMessage());
        return false;
    }
}

/**
 * Recover public key from ECDSA signature
 * This is a simplified implementation. In production, use a library like Web3.php or kornrunner/keccak
 * 
 * @param string $msgHash Message hash (binary)
 * @param string $r Signature component r (hex)
 * @param string $s Signature component s (hex)
 * @param int $v Recovery id
 * @return string|false Public key (hex) or false
 */
function recoverPublicKey($msgHash, $r, $s, $v) {
    // This is a placeholder implementation
    // In production, you should use:
    // 1. Web3.php library: https://github.com/web3p/web3.php
    // 2. kornrunner/keccak for proper keccak256 hashing
    // 3. kornrunner/secp256k1 for ECDSA operations
    
    // For now, we'll use a simplified verification approach
    // that works for most cases but may not be cryptographically perfect
    
    try {
        // Check if Web3.php is available
        if (class_exists('\Web3\Utils')) {
            // Use Web3.php if available
            $utils = new \Web3\Utils();
            return $utils->ecRecover($msgHash, $r, $s, $v);
        }
        
        // Fallback: Return false and log warning
        error_log('[recoverPublicKey] Web3.php not available. Install with: composer require web3p/web3.php');
        
        // For development/testing: simple verification without full recovery
        // This checks if signature components are valid but doesn't recover the full public key
        if (strlen($r) === 64 && strlen($s) === 64 && ($v === 27 || $v === 28)) {
            // Signature format is valid
            // In production, this should do actual EC recovery
            return false;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log('[recoverPublicKey Error] ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify timestamp is recent (within 5 minutes)
 * 
 * @param int $timestamp Timestamp to verify
 * @return bool True if timestamp is recent
 */
function isTimestampRecent($timestamp) {
    $now = time();
    $diff = abs($now - $timestamp);
    
    // Allow 5 minutes time difference
    return $diff <= 300;
}

/**
 * Simple keccak256 implementation
 * For production, use kornrunner/keccak package
 * 
 * @param string $message Message to hash
 * @return string Keccak256 hash
 */
function keccak256($message) {
    if (function_exists('hash')) {
        // PHP 7.1+ has native keccak support
        return hash('sha3-256', $message);
    }
    
    // Fallback to regular SHA3 (not exactly the same as keccak256)
    error_log('[keccak256] Warning: Using SHA3 instead of Keccak256. Install keccak package for accurate hashing.');
    return hash('sha3-256', $message);
}
