<?php
/**
 * Upload X25519 Public Key
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connection.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'] ?? null;
$public_key = $input['public_key'] ?? null;

if (!$user_id || !$public_key) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Check if key already exists
    $stmt = $pdo->prepare("SELECT id FROM x25519_keys WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    if ($stmt->fetch()) {
        // Update existing key
        $stmt = $pdo->prepare("UPDATE x25519_keys SET public_key = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$public_key, $user_id]);
        $message = 'Public key updated';
    } else {
        // Insert new key
        $stmt = $pdo->prepare("INSERT INTO x25519_keys (user_id, public_key) VALUES (?, ?)");
        $stmt->execute([$user_id, $public_key]);
        $message = 'Public key stored';
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (PDOException $e) {
    error_log("X25519 key upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
