<?php
/**
 * ============================================
 * GOVERNANCE BLOCKCHAIN SYNC
 * ============================================
 * Cron Job: php api/governance/cron/sync-governance.php
 * Run every 5 minutes to sync blockchain state
 * 
 * Tasks:
 * - Sync proposal states from blockchain
 * - Update vote counts
 * - Check quorum status
 * - Detect new proposals
 * - Update statistics
 * - Log sync operations
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'This script can only be run from command line'
    ]);
    exit();
}

// Set execution time limit
set_time_limit(300); // 5 minutes max

require_once __DIR__ . '/../helpers/governance-db.php';
require_once __DIR__ . '/../helpers/governance-web3.php';
require_once __DIR__ . '/../helpers/governance-utils.php';
require_once __DIR__ . '/../../../config/connection.php';

class GovernanceSync {
    private $db;
    private $web3;
    private $syncLog = [];
    private $startTime;
    
    public function __construct() {
        $this->db = new GovernanceDB();
        $this->web3 = new GovernanceWeb3();
        $this->startTime = microtime(true);
    }
    
    /**
     * Main sync process
     */
    public function run() {
        echo "===========================================\n";
        echo "GOVERNANCE SYNC STARTED\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        echo "===========================================\n\n";
        
        try {
            // Step 1: Sync proposal states
            echo "[1/5] Syncing proposal states...\n";
            $this->syncProposalStates();
            
            // Step 2: Sync vote counts
            echo "[2/5] Syncing vote counts...\n";
            $this->syncVoteCounts();
            
            // Step 3: Check quorum status
            echo "[3/5] Checking quorum status...\n";
            $this->checkQuorumStatus();
            
            // Step 4: Detect new proposals
            echo "[4/5] Detecting new proposals...\n";
            $this->detectNewProposals();
            
            // Step 5: Update statistics
            echo "[5/5] Updating statistics...\n";
            $this->updateStatistics();
            
            // Save sync log
            $this->saveSyncLog(true);
            
            $duration = round(microtime(true) - $this->startTime, 2);
            echo "\n===========================================\n";
            echo "SYNC COMPLETED SUCCESSFULLY\n";
            echo "Duration: {$duration} seconds\n";
            echo "===========================================\n";
            
        } catch (Exception $e) {
            $this->saveSyncLog(false, $e->getMessage());
            
            echo "\n===========================================\n";
            echo "SYNC FAILED\n";
            echo "Error: " . $e->getMessage() . "\n";
            echo "===========================================\n";
            
            error_log("Governance sync failed: " . $e->getMessage());
            exit(1);
        }
    }
    
    /**
     * Sync proposal states from blockchain
     */
    private function syncProposalStates() {
        global $conn;
        
        // Get all proposals that might need state update
        $stmt = $conn->prepare("
            SELECT proposal_id, status, voting_starts_at, voting_ends_at
            FROM governance_proposals
            WHERE status IN ('pending', 'active', 'queued')
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        $errors = 0;
        
        foreach ($proposals as $proposal) {
            try {
                // Get state from blockchain
                $onChainState = $this->web3->getProposalState($proposal['proposal_id']);
                $newStatus = $this->mapStateToStatus($onChainState);
                
                // Check if state changed
                if ($newStatus !== $proposal['status']) {
                    $updateStmt = $conn->prepare("
                        UPDATE governance_proposals
                        SET status = ?, updated_at = NOW()
                        WHERE proposal_id = ?
                    ");
                    $updateStmt->execute([$newStatus, $proposal['proposal_id']]);
                    
                    echo "  - Updated proposal {$proposal['proposal_id']}: {$proposal['status']} -> {$newStatus}\n";
                    $updated++;
                    
                    $this->syncLog[] = [
                        'action' => 'state_update',
                        'proposal_id' => $proposal['proposal_id'],
                        'old_status' => $proposal['status'],
                        'new_status' => $newStatus
                    ];
                }
                
            } catch (Exception $e) {
                echo "  - Error syncing proposal {$proposal['proposal_id']}: {$e->getMessage()}\n";
                $errors++;
            }
        }
        
        echo "  Updated: {$updated}, Errors: {$errors}\n\n";
    }
    
    /**
     * Sync vote counts from blockchain
     */
    private function syncVoteCounts() {
        global $conn;
        
        // Get active proposals
        $stmt = $conn->prepare("
            SELECT proposal_id
            FROM governance_proposals
            WHERE status = 'active'
            ORDER BY voting_ends_at ASC
            LIMIT 50
        ");
        $stmt->execute();
        $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        $errors = 0;
        
        foreach ($proposals as $proposal) {
            try {
                // Get votes from blockchain
                $votes = $this->web3->getProposalVotes($proposal['proposal_id']);
                
                // Update in database
                $updateStmt = $conn->prepare("
                    UPDATE governance_proposals
                    SET 
                        votes_for = ?,
                        votes_against = ?,
                        votes_abstain = ?,
                        updated_at = NOW()
                    WHERE proposal_id = ?
                ");
                $updateStmt->execute([
                    $votes['for'],
                    $votes['against'],
                    $votes['abstain'],
                    $proposal['proposal_id']
                ]);
                
                $updated++;
                
                $this->syncLog[] = [
                    'action' => 'vote_count_update',
                    'proposal_id' => $proposal['proposal_id'],
                    'votes' => $votes
                ];
                
            } catch (Exception $e) {
                echo "  - Error syncing votes for {$proposal['proposal_id']}: {$e->getMessage()}\n";
                $errors++;
            }
        }
        
        echo "  Updated: {$updated}, Errors: {$errors}\n\n";
    }
    
    /**
     * Check quorum status for active proposals
     */
    private function checkQuorumStatus() {
        global $conn;
        
        // Get active proposals
        $stmt = $conn->prepare("
            SELECT proposal_id, votes_for, votes_against, votes_abstain
            FROM governance_proposals
            WHERE status = 'active'
            ORDER BY voting_ends_at ASC
            LIMIT 50
        ");
        $stmt->execute();
        $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $checked = 0;
        
        foreach ($proposals as $proposal) {
            try {
                // Get total supply (for quorum calculation)
                $totalSupply = $this->web3->getTotalSupply();
                $totalVotes = bcadd(bcadd($proposal['votes_for'], $proposal['votes_against']), $proposal['votes_abstain']);
                
                // Calculate quorum (4%)
                $quorumRequired = bcmul($totalSupply, '0.04');
                $hasQuorum = bccomp($totalVotes, $quorumRequired) >= 0;
                
                // Check if proposal is passing
                $isPassing = bccomp($proposal['votes_for'], $proposal['votes_against']) > 0 && $hasQuorum;
                
                // Update database if needed
                $updateStmt = $conn->prepare("
                    UPDATE governance_proposals
                    SET quorum_reached = ?, is_passing = ?, updated_at = NOW()
                    WHERE proposal_id = ?
                ");
                $updateStmt->execute([
                    $hasQuorum ? 1 : 0,
                    $isPassing ? 1 : 0,
                    $proposal['proposal_id']
                ]);
                
                $checked++;
                
            } catch (Exception $e) {
                echo "  - Error checking quorum for {$proposal['proposal_id']}: {$e->getMessage()}\n";
            }
        }
        
        echo "  Checked: {$checked}\n\n";
    }
    
    /**
     * Detect new proposals from blockchain
     */
    private function detectNewProposals() {
        // This would require event listening or proposal ID iteration
        // For now, we'll skip since proposals are created through API
        echo "  Skipped (proposals created through API)\n\n";
    }
    
    /**
     * Update daily statistics
     */
    private function updateStatistics() {
        global $conn;
        
        $today = date('Y-m-d');
        
        // Get daily stats
        $stats = $this->db->getGovernanceStats();
        
        // Save to governance_stats table
        $stmt = $conn->prepare("
            INSERT INTO governance_stats 
            (date, total_proposals, total_voters, total_votes, total_voting_power, 
             stats_by_category, stats_by_status, participation_rate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_proposals = VALUES(total_proposals),
                total_voters = VALUES(total_voters),
                total_votes = VALUES(total_votes),
                total_voting_power = VALUES(total_voting_power),
                stats_by_category = VALUES(stats_by_category),
                stats_by_status = VALUES(stats_by_status),
                participation_rate = VALUES(participation_rate),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $today,
            $stats['total_proposals'],
            $stats['total_voters'],
            $stats['total_votes'],
            $stats['total_voting_power'],
            json_encode($stats['proposals_by_category']),
            json_encode($stats['proposals_by_status']),
            $stats['participation_rate']
        ]);
        
        echo "  Statistics updated for {$today}\n\n";
    }
    
    /**
     * Save sync log to database
     */
    private function saveSyncLog($success, $error = null) {
        global $conn;
        
        $duration = round(microtime(true) - $this->startTime, 2);
        
        $stmt = $conn->prepare("
            INSERT INTO governance_sync_log
            (sync_started_at, sync_completed_at, duration_seconds, success, 
             error_message, proposals_synced, votes_synced, sync_details)
            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)
        ");
        
        $proposalsSynced = count(array_filter($this->syncLog, function($log) {
            return $log['action'] === 'state_update';
        }));
        
        $votesSynced = count(array_filter($this->syncLog, function($log) {
            return $log['action'] === 'vote_count_update';
        }));
        
        $stmt->execute([
            date('Y-m-d H:i:s', $this->startTime),
            $duration,
            $success ? 1 : 0,
            $error,
            $proposalsSynced,
            $votesSynced,
            json_encode($this->syncLog)
        ]);
    }
    
    /**
     * Map blockchain state to database status
     */
    private function mapStateToStatus($state) {
        $stateMap = [
            0 => 'pending',
            1 => 'active',
            2 => 'canceled',
            3 => 'defeated',
            4 => 'succeeded',
            5 => 'queued',
            6 => 'expired',
            7 => 'executed'
        ];
        
        return $stateMap[$state] ?? 'unknown';
    }
}

// Run the sync
$sync = new GovernanceSync();
$sync->run();
