<?php
/**
 * Register and Manage Referrals
 * POST /api/staking/referral.php
 */

require_once '../../config/config.php';
require_once '../check_session.php';

header('Content-Type: application/json');

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $input['user_id'] ?? null;
    $action = $input['action'] ?? null; // 'register' or 'get_stats' or 'claim'
    $referrer_id = $input['referrer_id'] ?? null;
    $referral_code = $input['referral_code'] ?? null;
    $tx_hash = $input['tx_hash'] ?? null;
    
    // Validation
    if (!$user_id || !$action) {
        throw new Exception('Missing required fields');
    }
    
    if (!in_array($action, ['register', 'get_stats', 'claim'])) {
        throw new Exception('Invalid action');
    }
    
    // Database connection
    $conn = getDBConnection();
    
    if ($action === 'register') {
        // Register referral
        if (!$referrer_id && !$referral_code) {
            throw new Exception('Referrer ID or code required');
        }
        
        // Get referrer by code if provided
        if ($referral_code) {
            $code_stmt = $conn->prepare("
                SELECT user_id FROM staking_referral_codes 
                WHERE code = ? AND active = 1
            ");
            $code_stmt->bind_param("s", $referral_code);
            $code_stmt->execute();
            $code_result = $code_stmt->get_result();
            
            if ($code_result->num_rows === 0) {
                throw new Exception('Invalid referral code');
            }
            
            $referrer_id = $code_result->fetch_assoc()['user_id'];
        }
        
        // Validate
        if ($referrer_id == $user_id) {
            throw new Exception('Cannot refer yourself');
        }
        
        $conn->begin_transaction();
        
        try {
            // Check if user already has a referrer
            $check_stmt = $conn->prepare("
                SELECT referrer_id FROM staking_referrals WHERE user_id = ?
            ");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                throw new Exception('User already has a referrer');
            }
            
            // Register referral
            $stmt = $conn->prepare("
                INSERT INTO staking_referrals 
                (user_id, referrer_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->bind_param("ii", $user_id, $referrer_id);
            $stmt->execute();
            
            // Update referrer stats
            $update_stmt = $conn->prepare("
                UPDATE staking_referral_stats
                SET total_referred = total_referred + 1,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $update_stmt->bind_param("i", $referrer_id);
            $update_stmt->execute();
            
            // Create stats if not exists
            if ($update_stmt->affected_rows === 0) {
                $create_stmt = $conn->prepare("
                    INSERT INTO staking_referral_stats
                    (user_id, total_referred, created_at)
                    VALUES (?, 1, NOW())
                ");
                $create_stmt->bind_param("i", $referrer_id);
                $create_stmt->execute();
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Referral registered successfully',
                'data' => [
                    'user_id' => $user_id,
                    'referrer_id' => $referrer_id
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } elseif ($action === 'get_stats') {
        // Get referral statistics
        $stmt = $conn->prepare("
            SELECT 
                r.referrer_id,
                rs.total_referred,
                rs.total_rewards_earned,
                rs.bonus_apy,
                (SELECT COUNT(*) FROM staking_referrals WHERE referrer_id = ?) as referred_count,
                (SELECT GROUP_CONCAT(user_id) FROM staking_referrals WHERE referrer_id = ?) as referred_users
            FROM staking_referral_stats rs
            LEFT JOIN staking_referrals r ON r.referrer_id = rs.user_id
            WHERE rs.user_id = ?
        ");
        
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats = $result->fetch_assoc() ?: [
            'referrer_id' => null,
            'total_referred' => 0,
            'total_rewards_earned' => 0,
            'bonus_apy' => 0,
            'referred_count' => 0,
            'referred_users' => null
        ];
        
        // Get user's referral code
        $code_stmt = $conn->prepare("
            SELECT code FROM staking_referral_codes WHERE user_id = ? AND active = 1
        ");
        $code_stmt->bind_param("i", $user_id);
        $code_stmt->execute();
        $code_result = $code_stmt->get_result();
        $code = $code_result->num_rows > 0 ? $code_result->fetch_assoc()['code'] : null;
        
        // Generate code if not exists
        if (!$code) {
            $code = strtoupper(substr(md5($user_id . time()), 0, 8));
            $insert_code = $conn->prepare("
                INSERT INTO staking_referral_codes (user_id, code, active, created_at)
                VALUES (?, ?, 1, NOW())
            ");
            $insert_code->bind_param("is", $user_id, $code);
            $insert_code->execute();
        }
        
        $stats['referral_code'] = $code;
        $stats['referred_users'] = $stats['referred_users'] ? 
            explode(',', $stats['referred_users']) : [];
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } elseif ($action === 'claim') {
        // Claim referral rewards
        if (!$tx_hash) {
            throw new Exception('Transaction hash required');
        }
        
        $conn->begin_transaction();
        
        try {
            // Get unclaimed rewards
            $stmt = $conn->prepare("
                SELECT total_rewards_earned - total_rewards_claimed as unclaimed
                FROM staking_referral_stats
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('No referral stats found');
            }
            
            $unclaimed = $result->fetch_assoc()['unclaimed'];
            
            if ($unclaimed <= 0) {
                throw new Exception('No rewards to claim');
            }
            
            // Update claimed amount
            $update_stmt = $conn->prepare("
                UPDATE staking_referral_stats
                SET total_rewards_claimed = total_rewards_claimed + ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $update_stmt->bind_param("di", $unclaimed, $user_id);
            $update_stmt->execute();
            
            // Log transaction
            $log_stmt = $conn->prepare("
                INSERT INTO staking_transactions_log
                (user_id, transaction_type, amount, tx_hash, status, created_at)
                VALUES (?, 'referral_claim', ?, ?, 'confirmed', NOW())
            ");
            $log_stmt->bind_param("ids", $user_id, $unclaimed, $tx_hash);
            $log_stmt->execute();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Referral rewards claimed successfully',
                'data' => [
                    'amount' => $unclaimed,
                    'tx_hash' => $tx_hash
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
