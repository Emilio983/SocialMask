<?php
/**
 * ============================================
 * UPLOAD USER PRE-KEYS
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['user_id', 'identityKey', 'signedPreKey', 'preKeys', 'registrationId'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $userId = $input['user_id'];
    
    // Validate user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid user_id");
    }
    
    // Convert pre-keys array to JSON
    $oneTimePrekeys = json_encode($input['preKeys']);
    
    // Insert or update pre-keys
    $stmt = $conn->prepare("
        INSERT INTO user_prekeys (
            user_id,
            identity_key,
            signed_prekey_id,
            signed_prekey,
            prekey_signature,
            one_time_prekeys,
            registration_id,
            keys_generated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            identity_key = VALUES(identity_key),
            signed_prekey_id = VALUES(signed_prekey_id),
            signed_prekey = VALUES(signed_prekey),
            prekey_signature = VALUES(prekey_signature),
            one_time_prekeys = VALUES(one_time_prekeys),
            registration_id = VALUES(registration_id),
            keys_generated_at = NOW()
    ");
    
    $stmt->bind_param(
        'iiisssi',
        $userId,
        $input['identityKey'],
        $input['signedPreKey']['keyId'],
        $input['signedPreKey']['publicKey'],
        $input['signedPreKey']['signature'],
        $oneTimePrekeys,
        $input['registrationId']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to upload pre-keys");
    }
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Pre-keys uploaded successfully',
        'keys_count' => count($input['preKeys'])
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
