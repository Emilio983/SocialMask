<?php
/**
 * ============================================
 * GET USER PRE-KEYS
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        throw new Exception("Missing user_id");
    }
    
    $userId = $input['user_id'];
    
    // Get pre-keys from database
    $stmt = $conn->prepare("
        SELECT 
            identity_key,
            signed_prekey_id,
            signed_prekey,
            prekey_signature,
            one_time_prekeys,
            registration_id
        FROM user_prekeys
        WHERE user_id = ?
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("User has not uploaded pre-keys");
    }
    
    $row = $result->fetch_assoc();
    
    // Get one one-time pre-key
    $oneTimePrekeys = json_decode($row['one_time_prekeys'], true);
    $preKey = null;
    
    if (!empty($oneTimePrekeys)) {
        $preKey = array_shift($oneTimePrekeys);
        
        // Update remaining pre-keys
        $updatedKeys = json_encode($oneTimePrekeys);
        $updateStmt = $conn->prepare("
            UPDATE user_prekeys 
            SET one_time_prekeys = ?,
                last_used_at = NOW()
            WHERE user_id = ?
        ");
        $updateStmt->bind_param('si', $updatedKeys, $userId);
        $updateStmt->execute();
    }
    
    // Build pre-key bundle
    $preKeyBundle = [
        'registrationId' => (int)$row['registration_id'],
        'identityKey' => $row['identity_key'],
        'signedPreKey' => [
            'keyId' => (int)$row['signed_prekey_id'],
            'publicKey' => $row['signed_prekey'],
            'signature' => $row['prekey_signature']
        ]
    ];
    
    if ($preKey) {
        $preKeyBundle['preKey'] = $preKey;
    }
    
    sendJsonResponse([
        'success' => true,
        'preKeyBundle' => $preKeyBundle,
        'keys_remaining' => count($oneTimePrekeys)
    ]);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
