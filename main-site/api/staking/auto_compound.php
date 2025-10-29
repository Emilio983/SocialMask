<?php
/**
 * Enable/Disable Auto-Compound
 * POST /api/staking/auto_compound.php
 */

require_once '../../config/config.php';
require_once '../check_session.php';

header('Content-Type: application/json');

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $input['user_id'] ?? null;
    $action = $input['action'] ?? null; // 'enable', 'disable', or 'check'
    $frequency = $input['frequency'] ?? 86400; // Default 1 day
    $min_rewards = $input['min_rewards'] ?? 0;
    $tx_hash = $input['tx_hash'] ?? null;
    
    // Validation
    if (!$user_id || !$action) {
        throw new Exception('Missing required fields');
    }
    
    if (!in_array($action, ['enable', 'disable', 'check'])) {
        throw new Exception('Invalid action');
    }
    
    if ($action === 'enable') {
        if (!$tx_hash) {
            throw new Exception('Transaction hash required');
        }
        
        if ($frequency < 86400) {
            throw new Exception('Frequency must be at least 1 day');
        }
    }
    
    // Database connection
    $conn = getDBConnection();
    $conn->begin_transaction();
    
    try {
        if ($action === 'check') {
            // Check if auto-compound can be executed
            $stmt = $conn->prepare("
                SELECT 
                    ac.enabled,
                    ac.frequency,
                    ac.min_rewards,
                    ac.last_compound,
                    TIMESTAMPDIFF(SECOND, ac.last_compound, NOW()) as seconds_since_last,
                    COALESCE(SUM(sr.amount), 0) as pending_rewards
                FROM staking_auto_compound ac
                LEFT JOIN staking_stats ss ON ac.user_id = ss.user_id
                LEFT JOIN staking_rewards sr ON ac.user_id = sr.user_id AND sr.claimed_at IS NULL
                WHERE ac.user_id = ?
                GROUP BY ac.user_id
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $check_data = $result->fetch_assoc();
            
            if (!$check_data || !$check_data['enabled']) {
                throw new Exception('Auto-compound not enabled');
            }
            
            $can_execute = true;
            $reasons = [];
            
            // Check frequency
            if ($check_data['seconds_since_last'] < $check_data['frequency']) {
                $can_execute = false;
                $remaining = $check_data['frequency'] - $check_data['seconds_since_last'];
                $reasons[] = "Too soon to compound. Wait " . gmdate("H:i:s", $remaining);
            }
            
            // Check minimum rewards
            if ($check_data['pending_rewards'] < $check_data['min_rewards']) {
                $can_execute = false;
                $reasons[] = "Pending rewards ({$check_data['pending_rewards']}) below minimum ({$check_data['min_rewards']})";
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'can_execute' => $can_execute,
                    'reasons' => $reasons,
                    'pending_rewards' => (float)$check_data['pending_rewards'],
                    'min_rewards' => (float)$check_data['min_rewards'],
                    'seconds_since_last' => (int)$check_data['seconds_since_last'],
                    'frequency' => (int)$check_data['frequency'],
                    'next_compound_in' => max(0, $check_data['frequency'] - $check_data['seconds_since_last'])
                ]
            ]);
            
        } else if ($action === 'enable') {
            // Enable auto-compound
            $stmt = $conn->prepare("
                INSERT INTO staking_auto_compound 
                (user_id, enabled, frequency, min_rewards, tx_hash, created_at)
                VALUES (?, 1, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    enabled = 1,
                    frequency = VALUES(frequency),
                    min_rewards = VALUES(min_rewards),
                    tx_hash = VALUES(tx_hash),
                    updated_at = NOW()
            ");
            
            $stmt->bind_param("iids", $user_id, $frequency, $min_rewards, $tx_hash);
            $stmt->execute();
            
            // Log transaction
            $log_stmt = $conn->prepare("
                INSERT INTO staking_transactions_log
                (user_id, transaction_type, amount, tx_hash, status, created_at)
                VALUES (?, 'auto_compound_enable', 0, ?, 'confirmed', NOW())
            ");
            $log_stmt->bind_param("is", $user_id, $tx_hash);
            $log_stmt->execute();
            
            $message = 'Auto-compound enabled successfully';
            
        } else {
            // Disable auto-compound
            $stmt = $conn->prepare("
                UPDATE staking_auto_compound
                SET enabled = 0, updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $message = 'Auto-compound disabled successfully';
        }
        
        // Get current settings
        $settings_stmt = $conn->prepare("
            SELECT enabled, frequency, min_rewards, last_compound, 
                   TIMESTAMPDIFF(SECOND, last_compound, NOW()) as seconds_since_last
            FROM staking_auto_compound
            WHERE user_id = ?
        ");
        $settings_stmt->bind_param("i", $user_id);
        $settings_stmt->execute();
        $settings_result = $settings_stmt->get_result();
        $settings = $settings_result->fetch_assoc();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => [
                'enabled' => (bool)($settings['enabled'] ?? false),
                'frequency' => (int)($settings['frequency'] ?? 0),
                'min_rewards' => (float)($settings['min_rewards'] ?? 0),
                'last_compound' => $settings['last_compound'] ?? null,
                'next_compound_in' => $settings['enabled'] ? 
                    max(0, $settings['frequency'] - $settings['seconds_since_last']) : null
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
