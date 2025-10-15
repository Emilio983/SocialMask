<?php
/**
 * Get Advanced Analytics
 * GET /api/staking/analytics.php
 */

require_once '../../config/config.php';
require_once '../check_session.php';

header('Content-Type: application/json');

try {
    $user_id = $_GET['user_id'] ?? null;
    $type = $_GET['type'] ?? 'user'; // 'user' or 'global'
    
    // Database connection
    $conn = getDBConnection();
    
    if ($type === 'user') {
        if (!$user_id) {
            throw new Exception('User ID required');
        }
        
        // Get comprehensive user analytics
        $stmt = $conn->prepare("CALL sp_get_user_advanced_stats(?)");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        
        // Get compound history
        $compound_stmt = $conn->prepare("
            SELECT 
                amount,
                fee,
                executor_address,
                tx_hash,
                created_at,
                DATE_FORMAT(created_at, '%Y-%m-%d') as date
            FROM staking_compound_history
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $compound_stmt->bind_param("i", $user_id);
        $compound_stmt->execute();
        $compound_history = $compound_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get referral tree
        $referral_stmt = $conn->prepare("
            SELECT 
                r.user_id,
                u.username,
                sd.amount as staked,
                r.created_at
            FROM staking_referrals r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN staking_deposits sd ON r.user_id = sd.user_id AND sd.status = 'active'
            WHERE r.referrer_id = ?
            ORDER BY r.created_at DESC
        ");
        $referral_stmt->bind_param("i", $user_id);
        $referral_stmt->execute();
        $referrals = $referral_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get multi-pool positions
        $multipool_stmt = $conn->prepare("
            SELECT 
                mpp.pool_id,
                pi.pool_name,
                mpp.amount,
                mpp.start_time,
                mpp.lock_end_time,
                mpp.total_rewards_claimed,
                pi.apy,
                CASE 
                    WHEN mpp.lock_end_time IS NULL THEN 1
                    WHEN mpp.lock_end_time > NOW() THEN 0
                    ELSE 1
                END as can_unstake
            FROM staking_multi_pool_positions mpp
            JOIN staking_pools_info pi ON mpp.pool_id = pi.pool_id
            WHERE mpp.user_id = ? AND mpp.status = 'active'
        ");
        $multipool_stmt->bind_param("i", $user_id);
        $multipool_stmt->execute();
        $multi_pool_positions = $multipool_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate performance metrics
        $total_earned = ($stats['total_rewards'] ?? 0) + 
                       ($stats['referral_rewards_earned'] ?? 0) + 
                       ($stats['total_compounded'] ?? 0);
        
        $total_invested = ($stats['total_staked'] ?? 0) - ($stats['total_compounded'] ?? 0);
        $roi = $total_invested > 0 ? ($total_earned / $total_invested) * 100 : 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'user_stats' => $stats,
                'performance' => [
                    'total_invested' => $total_invested,
                    'total_earned' => $total_earned,
                    'roi_percentage' => round($roi, 2),
                    'compound_count' => $stats['compound_count'] ?? 0,
                    'referral_count' => $stats['total_referred'] ?? 0
                ],
                'compound_history' => $compound_history,
                'referral_tree' => $referrals,
                'multi_pool_positions' => $multi_pool_positions,
                'governance' => [
                    'tokens_earned' => $stats['governance_tokens_earned'] ?? 0,
                    'tokens_claimed' => $stats['governance_tokens_claimed'] ?? 0,
                    'tokens_available' => ($stats['governance_tokens_earned'] ?? 0) - 
                                        ($stats['governance_tokens_claimed'] ?? 0)
                ]
            ]
        ]);
        
    } elseif ($type === 'global') {
        // Get global platform analytics
        
        // Total stats
        $total_stmt = $conn->query("
            SELECT 
                COUNT(DISTINCT user_id) as total_users,
                SUM(amount) as total_staked,
                SUM(total_rewards_claimed) as total_rewards_distributed
            FROM staking_deposits
            WHERE status = 'active'
        ");
        $totals = $total_stmt->fetch_assoc();
        
        // Auto-compound stats
        $ac_stmt = $conn->query("
            SELECT 
                COUNT(*) as active_users,
                SUM(total_compounded) as total_compounded,
                SUM(compound_count) as total_compounds,
                AVG(frequency) as avg_frequency
            FROM staking_auto_compound
            WHERE enabled = 1
        ");
        $ac_stats = $ac_stmt->fetch_assoc();
        
        // Referral stats
        $ref_stmt = $conn->query("
            SELECT 
                COUNT(DISTINCT referrer_id) as total_referrers,
                COUNT(*) as total_referrals,
                SUM(total_rewards_earned) as total_referral_rewards
            FROM staking_referral_stats
        ");
        $ref_stats = $ref_stmt->fetch_assoc();
        
        // Pool distribution
        $pool_stmt = $conn->query("
            SELECT 
                pi.pool_id,
                pi.pool_name,
                pi.apy,
                pi.lock_period_days,
                SUM(sd.amount) as total_staked,
                COUNT(DISTINCT sd.user_id) as stakers_count,
                (SUM(sd.amount) / (SELECT SUM(amount) FROM staking_deposits WHERE status = 'active')) * 100 as percentage
            FROM staking_deposits sd
            JOIN staking_pools_info pi ON sd.pool_id = pi.pool_id
            WHERE sd.status = 'active'
            GROUP BY pi.pool_id, pi.pool_name, pi.apy, pi.lock_period_days
            ORDER BY total_staked DESC
        ");
        $pool_distribution = $pool_stmt->fetch_all(MYSQLI_ASSOC);
        
        // Activity over time (last 30 days)
        $activity_stmt = $conn->query("
            SELECT 
                DATE(created_at) as date,
                transaction_type,
                COUNT(*) as count,
                SUM(amount) as volume
            FROM staking_transactions_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at), transaction_type
            ORDER BY date DESC
        ");
        $activity = $activity_stmt->fetch_all(MYSQLI_ASSOC);
        
        // Top stakers
        $top_stmt = $conn->query("
            SELECT 
                u.id,
                u.username,
                sd.amount as staked,
                ss.total_rewards_claimed,
                rs.total_referred,
                ac.total_compounded
            FROM users u
            JOIN staking_deposits sd ON u.id = sd.user_id AND sd.status = 'active'
            LEFT JOIN staking_stats ss ON u.id = ss.user_id
            LEFT JOIN staking_referral_stats rs ON u.id = rs.user_id
            LEFT JOIN staking_auto_compound ac ON u.id = ac.user_id
            ORDER BY sd.amount DESC
            LIMIT 10
        ");
        $top_stakers = $top_stmt->fetch_all(MYSQLI_ASSOC);
        
        // Top referrers (from view)
        $top_ref_stmt = $conn->query("
            SELECT * FROM v_referral_leaderboard LIMIT 10
        ");
        $top_referrers = $top_ref_stmt->fetch_all(MYSQLI_ASSOC);
        
        // Governance tokens
        $gov_stmt = $conn->query("
            SELECT 
                SUM(tokens_earned) as total_earned,
                SUM(tokens_claimed) as total_claimed,
                COUNT(DISTINCT user_id) as holders_count
            FROM staking_governance_tokens
        ");
        $gov_stats = $gov_stmt->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_value_locked' => $totals['total_staked'] ?? 0,
                    'total_users' => $totals['total_users'] ?? 0,
                    'total_rewards_distributed' => $totals['total_rewards_distributed'] ?? 0,
                    'timestamp' => date('Y-m-d H:i:s')
                ],
                'auto_compound' => [
                    'active_users' => $ac_stats['active_users'] ?? 0,
                    'total_compounded' => $ac_stats['total_compounded'] ?? 0,
                    'total_compounds' => $ac_stats['total_compounds'] ?? 0,
                    'avg_frequency_days' => round(($ac_stats['avg_frequency'] ?? 0) / 86400, 2)
                ],
                'referrals' => [
                    'total_referrers' => $ref_stats['total_referrers'] ?? 0,
                    'total_referrals' => $ref_stats['total_referrals'] ?? 0,
                    'total_rewards_paid' => $ref_stats['total_referral_rewards'] ?? 0
                ],
                'pool_distribution' => $pool_distribution,
                'activity_30d' => $activity,
                'top_stakers' => $top_stakers,
                'top_referrers' => $top_referrers,
                'governance' => [
                    'total_tokens_earned' => $gov_stats['total_earned'] ?? 0,
                    'total_tokens_claimed' => $gov_stats['total_claimed'] ?? 0,
                    'token_holders' => $gov_stats['holders_count'] ?? 0
                ]
            ]
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
