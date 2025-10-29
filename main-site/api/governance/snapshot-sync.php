<?php
/**
 * ============================================
 * SNAPSHOT.ORG SYNCHRONIZATION API
 * ============================================
 * Sync proposals and votes from Snapshot to local database
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/response_helper.php';

header('Content-Type: application/json');

class SnapshotSync {
    private $conn;
    private $graphqlEndpoint = 'https://hub.snapshot.org/graphql';
    private $space = 'sphera.eth';
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $stmt = $this->conn->prepare("
            SELECT space, graphql_endpoint 
            FROM governance_snapshot_config 
            WHERE is_active = TRUE 
            LIMIT 1
        ");
        $stmt->execute();
        $config = $stmt->get_result()->fetch_assoc();
        
        if ($config) {
            $this->space = $config['space'];
            $this->graphqlEndpoint = $config['graphql_endpoint'];
        }
    }
    
    /**
     * Fetch proposals from Snapshot
     */
    public function fetchProposals($limit = 20) {
        $query = '{
            proposals(
                first: ' . $limit . ',
                skip: 0,
                where: {
                    space: "' . $this->space . '"
                },
                orderBy: "created",
                orderDirection: desc
            ) {
                id
                ipfs
                title
                body
                choices
                start
                end
                snapshot
                state
                scores
                scores_total
                votes
                author
                created
            }
        }';
        
        $response = $this->graphqlRequest($query);
        
        if ($response && isset($response['data']['proposals'])) {
            return $response['data']['proposals'];
        }
        
        return [];
    }
    
    /**
     * Fetch votes for a proposal
     */
    public function fetchVotes($proposalId, $limit = 1000) {
        $query = '{
            votes(
                first: ' . $limit . ',
                where: {
                    proposal: "' . $proposalId . '"
                },
                orderBy: "created",
                orderDirection: desc
            ) {
                id
                voter
                created
                choice
                vp
                reason
            }
        }';
        
        $response = $this->graphqlRequest($query);
        
        if ($response && isset($response['data']['votes'])) {
            return $response['data']['votes'];
        }
        
        return [];
    }
    
    /**
     * GraphQL request helper
     */
    private function graphqlRequest($query) {
        $ch = curl_init($this->graphqlEndpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $query]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    /**
     * Sync proposals to database
     */
    public function syncProposals() {
        $proposals = $this->fetchProposals(50);
        $synced = 0;
        
        foreach ($proposals as $proposal) {
            $stmt = $this->conn->prepare("
                INSERT INTO governance_snapshot_proposals 
                (snapshot_id, ipfs_hash, title, body, choices, start_timestamp, end_timestamp, 
                 scores, scores_total, votes_count, state, proposer_address, space)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    body = VALUES(body),
                    scores = VALUES(scores),
                    scores_total = VALUES(scores_total),
                    votes_count = VALUES(votes_count),
                    state = VALUES(state),
                    last_synced_at = CURRENT_TIMESTAMP
            ");
            
            $state = strtoupper($proposal['state']);
            $choices = json_encode($proposal['choices']);
            $scores = json_encode($proposal['scores']);
            
            $stmt->bind_param(
                'sssssiiidisss',
                $proposal['id'],
                $proposal['ipfs'],
                $proposal['title'],
                $proposal['body'],
                $choices,
                $proposal['start'],
                $proposal['end'],
                $scores,
                $proposal['scores_total'],
                $proposal['votes'],
                $state,
                $proposal['author'],
                $this->space
            );
            
            if ($stmt->execute()) {
                $synced++;
                
                // Sync votes for this proposal
                if ($proposal['votes'] > 0) {
                    $this->syncVotesForProposal($proposal['id']);
                }
            }
        }
        
        return $synced;
    }
    
    /**
     * Sync votes for specific proposal
     */
    private function syncVotesForProposal($proposalId) {
        $votes = $this->fetchVotes($proposalId);
        $synced = 0;
        
        foreach ($votes as $vote) {
            $stmt = $this->conn->prepare("
                INSERT INTO governance_snapshot_votes 
                (vote_id, snapshot_id, voter_address, choice, voting_power, reason, created_at_timestamp)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    voting_power = VALUES(voting_power),
                    synced_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->bind_param(
                'sssidsi',
                $vote['id'],
                $proposalId,
                $vote['voter'],
                $vote['choice'],
                $vote['vp'],
                $vote['reason'],
                $vote['created']
            );
            
            if ($stmt->execute()) {
                $synced++;
            }
        }
        
        return $synced;
    }
    
    /**
     * Log sync operation
     */
    private function logSync($type, $proposals, $votes, $status, $error = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO governance_snapshot_sync_log 
            (space, sync_type, proposals_synced, votes_synced, status, error_message, started_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param('ssiiss', $this->space, $type, $proposals, $votes, $status, $error);
        $stmt->execute();
    }
    
    /**
     * Full sync
     */
    public function fullSync() {
        $startTime = microtime(true);
        
        try {
            $proposalsSynced = $this->syncProposals();
            
            // Count votes synced
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM governance_snapshot_votes 
                WHERE synced_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $votesSynced = $result['count'];
            
            $this->logSync('FULL', $proposalsSynced, $votesSynced, 'SUCCESS');
            
            $duration = round((microtime(true) - $startTime) * 1000);
            
            return [
                'success' => true,
                'proposals_synced' => $proposalsSynced,
                'votes_synced' => $votesSynced,
                'duration_ms' => $duration
            ];
            
        } catch (Exception $e) {
            $this->logSync('FULL', 0, 0, 'FAILED', $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Handle request
try {
    $sync = new SnapshotSync($conn);
    $result = $sync->fullSync();
    
    sendJsonResponse($result);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
