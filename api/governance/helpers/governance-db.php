<?php
/**
 * ============================================
 * GOVERNANCE DATABASE HELPER
 * ============================================
 * Functions for database operations related to governance
 */

require_once __DIR__ . '/../../../config/connection.php';

class GovernanceDB {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Save new proposal to database
     */
    public function saveProposal(array $data): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO governance_proposals (
                    proposal_id, proposer_user_id, proposer_wallet, category, title, description,
                    targets, values, calldatas, status, on_chain_tx,
                    voting_starts_at, voting_ends_at, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            return $stmt->execute([
                $data['proposal_id'],
                $data['proposer_user_id'],
                $data['proposer_wallet'],
                $data['category'],
                $data['title'],
                $data['description'],
                $data['targets'], // Already JSON encoded
                $data['values'], // Already JSON encoded
                $data['calldatas'], // Already JSON encoded
                $data['status'] ?? 'pending',
                $data['on_chain_tx'] ?? null,
                $data['voting_starts_at'] ?? null,
                $data['voting_ends_at'] ?? null,
                $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        } catch (PDOException $e) {
            error_log("Error saving proposal: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update proposal status and votes
     */
    public function updateProposal(string $proposalId, array $updates): bool {
        try {
            $fields = [];
            $values = [];
            
            foreach ($updates as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            
            $values[] = $proposalId;
            
            $sql = "UPDATE governance_proposals SET " . implode(', ', $fields) . " WHERE proposal_id = ?";
            $stmt = $this->conn->prepare($sql);
            
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Error updating proposal: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get proposals with filters and pagination
     */
    public function getProposals(array $filters = [], int $page = 1, int $limit = 20): array {
        try {
            $where = [];
            $params = [];
            
            // Apply filters
            if (isset($filters['category'])) {
                $where[] = "p.category = ?";
                $params[] = $filters['category'];
            }
            
            if (isset($filters['status'])) {
                $where[] = "p.status = ?";
                $params[] = $filters['status'];
            }
            
            if (isset($filters['user_id'])) {
                $where[] = "p.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (isset($filters['search'])) {
                $where[] = "MATCH(p.title, p.description) AGAINST (? IN BOOLEAN MODE)";
                $params[] = $filters['search'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM governance_proposals p $whereClause";
            $countStmt = $this->conn->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get proposals
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT 
                    p.*,
                    u.username as proposer_username,
                    u.avatar_url as proposer_avatar,
                    (p.votes_for + p.votes_against + p.votes_abstain) as total_votes,
                    CASE 
                        WHEN p.votes_for > p.votes_against THEN TRUE
                        ELSE FALSE
                    END as is_passing,
                    (SELECT COUNT(*) FROM governance_votes v WHERE v.proposal_id = p.proposal_id) as vote_count
                FROM governance_proposals p
                LEFT JOIN users u ON p.user_id = u.id
                $whereClause
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($proposals as &$proposal) {
                $proposal['targets'] = json_decode($proposal['targets'], true);
                $proposal['values'] = json_decode($proposal['values'], true);
                $proposal['calldatas'] = json_decode($proposal['calldatas'], true);
            }
            
            return [
                'proposals' => $proposals,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Error getting proposals: " . $e->getMessage());
            return ['proposals' => [], 'pagination' => []];
        }
    }
    
    /**
     * Get single proposal with full details
     */
    public function getProposalDetail(string $proposalId): ?array {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    p.*,
                    u.id as proposer_user_id,
                    u.username as proposer_username,
                    u.wallet_address as proposer_wallet,
                    u.avatar_url as proposer_avatar,
                    (p.votes_for + p.votes_against + p.votes_abstain) as total_votes,
                    CASE 
                        WHEN p.votes_for > p.votes_against THEN TRUE
                        ELSE FALSE
                    END as is_passing
                FROM governance_proposals p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.proposal_id = ?
            ");
            
            $stmt->execute([$proposalId]);
            $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$proposal) {
                return null;
            }
            
            // Decode JSON fields
            $proposal['targets'] = json_decode($proposal['targets'], true);
            $proposal['values'] = json_decode($proposal['values'], true);
            $proposal['calldatas'] = json_decode($proposal['calldatas'], true);
            
            // Get votes breakdown
            $proposal['votes_breakdown'] = $this->getProposalVotes($proposalId);
            
            return $proposal;
        } catch (PDOException $e) {
            error_log("Error getting proposal detail: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get votes for a proposal
     */
    public function getProposalVotes(string $proposalId, int $limit = 50): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    v.*,
                    u.username,
                    u.avatar_url,
                    CASE v.vote_type
                        WHEN 0 THEN 'against'
                        WHEN 1 THEN 'for'
                        WHEN 2 THEN 'abstain'
                    END as vote_type_name
                FROM governance_votes v
                LEFT JOIN users u ON v.user_id = u.id
                WHERE v.proposal_id = ?
                ORDER BY v.voting_power DESC, v.voted_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$proposalId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting proposal votes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Save vote
     */
    public function saveVote(array $voteData): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO governance_votes (
                    proposal_id, user_id, wallet_address, vote_type, 
                    voting_power, reason, on_chain_tx
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    vote_type = VALUES(vote_type),
                    voting_power = VALUES(voting_power),
                    reason = VALUES(reason),
                    on_chain_tx = VALUES(on_chain_tx),
                    voted_at = CURRENT_TIMESTAMP
            ");
            
            $success = $stmt->execute([
                $voteData['proposal_id'],
                $voteData['user_id'],
                $voteData['wallet_address'],
                $voteData['vote_type'],
                $voteData['voting_power'],
                $voteData['reason'] ?? null,
                $voteData['on_chain_tx'] ?? null
            ]);
            
            // Update proposal vote counts
            if ($success) {
                $this->updateProposalVoteCounts($voteData['proposal_id']);
            }
            
            return $success;
        } catch (PDOException $e) {
            error_log("Error saving vote: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update proposal vote counts
     */
    private function updateProposalVoteCounts(string $proposalId): bool {
        try {
            $stmt = $this->conn->prepare("
                UPDATE governance_proposals p
                SET 
                    votes_for = (
                        SELECT COALESCE(SUM(voting_power), 0) 
                        FROM governance_votes 
                        WHERE proposal_id = ? AND vote_type = 1
                    ),
                    votes_against = (
                        SELECT COALESCE(SUM(voting_power), 0) 
                        FROM governance_votes 
                        WHERE proposal_id = ? AND vote_type = 0
                    ),
                    votes_abstain = (
                        SELECT COALESCE(SUM(voting_power), 0) 
                        FROM governance_votes 
                        WHERE proposal_id = ? AND vote_type = 2
                    )
                WHERE proposal_id = ?
            ");
            
            return $stmt->execute([$proposalId, $proposalId, $proposalId, $proposalId]);
        } catch (PDOException $e) {
            error_log("Error updating vote counts: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has voted on proposal
     */
    public function hasUserVoted(string $proposalId, string $walletAddress): bool {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM governance_votes 
                WHERE proposal_id = ? AND wallet_address = ?
            ");
            
            $stmt->execute([$proposalId, $walletAddress]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Error checking if user voted: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save delegation
     */
    public function saveDelegation(array $data): bool {
        try {
            // Revoke existing active delegations
            $stmt = $this->conn->prepare("
                UPDATE governance_delegations
                SET revoked_at = CURRENT_TIMESTAMP
                WHERE wallet_address = ? AND revoked_at IS NULL
            ");
            $stmt->execute([$data['wallet_address']]);
            
            // Insert new delegation
            $stmt = $this->conn->prepare("
                INSERT INTO governance_delegations (
                    user_id, wallet_address, delegatee, voting_power, on_chain_tx
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['user_id'],
                $data['wallet_address'],
                $data['delegatee'],
                $data['voting_power'],
                $data['on_chain_tx'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error saving delegation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active delegation for wallet
     */
    public function getActiveDelegation(string $walletAddress): ?array {
        try {
            $stmt = $this->conn->prepare("
                SELECT *
                FROM governance_delegations
                WHERE wallet_address = ? AND revoked_at IS NULL
                ORDER BY delegated_at DESC
                LIMIT 1
            ");
            
            $stmt->execute([$walletAddress]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Error getting active delegation: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get governance statistics
     */
    public function getGovernanceStats(): array {
        try {
            // Get latest stats or create new one
            $stmt = $this->conn->query("
                SELECT * FROM governance_stats 
                WHERE stat_date = CURDATE()
                LIMIT 1
            ");
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stats) {
                // Create stats for today
                $this->updateDailyStats();
                $stmt = $this->conn->query("
                    SELECT * FROM governance_stats 
                    WHERE stat_date = CURDATE()
                    LIMIT 1
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Decode JSON fields
            $stats['proposals_by_category'] = json_decode($stats['proposals_by_category'] ?? '{}', true);
            $stats['proposals_by_status'] = json_decode($stats['proposals_by_status'] ?? '{}', true);
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting governance stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update daily statistics
     */
    public function updateDailyStats(): bool {
        try {
            // Get counts
            $totalProposals = $this->conn->query("SELECT COUNT(*) as count FROM governance_proposals")->fetch()['count'];
            $activeProposals = $this->conn->query("SELECT COUNT(*) as count FROM governance_proposals WHERE status = 'active'")->fetch()['count'];
            $totalVoters = $this->conn->query("SELECT COUNT(DISTINCT wallet_address) as count FROM governance_votes")->fetch()['count'];
            $totalVotes = $this->conn->query("SELECT COUNT(*) as count FROM governance_votes")->fetch()['count'];
            
            // Get proposals by category
            $categoryCounts = $this->conn->query("
                SELECT category, COUNT(*) as count 
                FROM governance_proposals 
                GROUP BY category
            ")->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $proposalsByCategory = [
                'parameter_change' => $categoryCounts[0] ?? 0,
                'treasury_management' => $categoryCounts[1] ?? 0,
                'contract_upgrade' => $categoryCounts[2] ?? 0,
                'feature_proposal' => $categoryCounts[3] ?? 0,
                'emergency_action' => $categoryCounts[4] ?? 0
            ];
            
            // Get proposals by status
            $statusCounts = $this->conn->query("
                SELECT status, COUNT(*) as count 
                FROM governance_proposals 
                GROUP BY status
            ")->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $proposalsByStatus = [
                'pending' => $statusCounts['pending'] ?? 0,
                'active' => $statusCounts['active'] ?? 0,
                'succeeded' => $statusCounts['succeeded'] ?? 0,
                'defeated' => $statusCounts['defeated'] ?? 0,
                'queued' => $statusCounts['queued'] ?? 0,
                'executed' => $statusCounts['executed'] ?? 0,
                'cancelled' => $statusCounts['cancelled'] ?? 0
            ];
            
            // Calculate average participation
            $avgParticipation = $totalProposals > 0 ? ($totalVotes / $totalProposals) * 100 : 0;
            
            // Insert or update
            $stmt = $this->conn->prepare("
                INSERT INTO governance_stats (
                    stat_date, total_proposals, active_proposals, total_voters, 
                    total_votes_cast, average_participation, proposals_by_category, 
                    proposals_by_status
                ) VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_proposals = VALUES(total_proposals),
                    active_proposals = VALUES(active_proposals),
                    total_voters = VALUES(total_voters),
                    total_votes_cast = VALUES(total_votes_cast),
                    average_participation = VALUES(average_participation),
                    proposals_by_category = VALUES(proposals_by_category),
                    proposals_by_status = VALUES(proposals_by_status)
            ");
            
            return $stmt->execute([
                $totalProposals,
                $activeProposals,
                $totalVoters,
                $totalVotes,
                $avgParticipation,
                json_encode($proposalsByCategory),
                json_encode($proposalsByStatus)
            ]);
        } catch (PDOException $e) {
            error_log("Error updating daily stats: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user governance activity
     */
    public function getUserActivity(int $userId): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM v_user_governance_activity
                WHERE user_id = ?
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting user activity: " . $e->getMessage());
            return [];
        }
    }
}
