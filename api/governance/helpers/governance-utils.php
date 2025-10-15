<?php
/**
 * ============================================
 * GOVERNANCE UTILITIES
 * ============================================
 * Helper functions for governance system
 */

class GovernanceUtils {
    
    /**
     * Get category name from number
     */
    public static function getCategoryName(int $category): string {
        $categories = [
            0 => 'Parameter Change',
            1 => 'Treasury Management',
            2 => 'Contract Upgrade',
            3 => 'Feature Proposal',
            4 => 'Emergency Action'
        ];
        
        return $categories[$category] ?? 'Unknown';
    }
    
    /**
     * Get state name from number
     */
    public static function getStateName(int $state): string {
        $states = [
            0 => 'pending',
            1 => 'active',
            2 => 'cancelled',
            3 => 'defeated',
            4 => 'succeeded',
            5 => 'queued',
            6 => 'expired',
            7 => 'executed'
        ];
        
        return $states[$state] ?? 'unknown';
    }
    
    /**
     * Validate wallet address format
     */
    public static function isValidWalletAddress(string $address): bool {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }
    
    /**
     * Validate proposal ID format
     */
    public static function isValidProposalId(string $proposalId): bool {
        // Proposal ID is a uint256 (can be very large number or hex)
        return preg_match('/^(0x[a-fA-F0-9]{1,64}|[0-9]{1,78})$/', $proposalId) === 1;
    }
    
    /**
     * Validate transaction hash format
     */
    public static function isValidTxHash(string $txHash): bool {
        return preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash) === 1;
    }
    
    /**
     * Validate category
     */
    public static function isValidCategory(int $category): bool {
        return $category >= 0 && $category <= 4;
    }
    
    /**
     * Validate vote type
     */
    public static function isValidVoteType(int $voteType): bool {
        return in_array($voteType, [0, 1, 2]); // 0=Against, 1=For, 2=Abstain
    }
    
    /**
     * Validate status
     */
    public static function isValidStatus(string $status): bool {
        $validStatuses = ['pending', 'active', 'succeeded', 'defeated', 'queued', 'executed', 'cancelled'];
        return in_array($status, $validStatuses);
    }
    
    /**
     * Sanitize proposal description (markdown)
     */
    public static function sanitizeDescription(string $description): string {
        // Remove any script tags
        $description = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $description);
        
        // Remove any iframe tags
        $description = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $description);
        
        // Limit length
        if (strlen($description) > 10000) {
            $description = substr($description, 0, 10000);
        }
        
        return trim($description);
    }
    
    /**
     * Sanitize title
     */
    public static function sanitizeTitle(string $title): string {
        // Remove HTML tags
        $title = strip_tags($title);
        
        // Limit length
        if (strlen($title) > 255) {
            $title = substr($title, 0, 255);
        }
        
        return trim($title);
    }
    
    /**
     * Calculate time remaining for voting
     */
    public static function getTimeRemaining(string $endTime): array {
        $now = new DateTime();
        $end = new DateTime($endTime);
        
        if ($now >= $end) {
            return [
                'ended' => true,
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0
            ];
        }
        
        $diff = $now->diff($end);
        
        return [
            'ended' => false,
            'days' => $diff->days,
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'seconds' => $diff->s,
            'total_seconds' => ($diff->days * 86400) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s
        ];
    }
    
    /**
     * Format voting power for display
     */
    public static function formatVotingPower(string $wei): string {
        // Convert wei to ether (18 decimals)
        $ether = bcdiv($wei, '1000000000000000000', 18);
        
        // Remove trailing zeros
        $ether = rtrim(rtrim($ether, '0'), '.');
        
        // Format with thousand separators
        $formatted = number_format((float)$ether, 2, '.', ',');
        
        return $formatted . ' GOVSPHE';
    }
    
    /**
     * Calculate vote percentage
     */
    public static function calculateVotePercentage(string $votes, string $totalVotes): float {
        if ($totalVotes === '0' || $totalVotes === '') {
            return 0.0;
        }
        
        $percentage = bcdiv(
            bcmul($votes, '100', 0),
            $totalVotes,
            2
        );
        
        return (float) $percentage;
    }
    
    /**
     * Get proposal progress data
     */
    public static function getProposalProgress(array $proposal): array {
        $totalVotes = bcadd(
            bcadd($proposal['votes_for'], $proposal['votes_against']),
            $proposal['votes_abstain']
        );
        
        $forPercentage = self::calculateVotePercentage($proposal['votes_for'], $totalVotes);
        $againstPercentage = self::calculateVotePercentage($proposal['votes_against'], $totalVotes);
        $abstainPercentage = self::calculateVotePercentage($proposal['votes_abstain'], $totalVotes);
        
        return [
            'total_votes' => $totalVotes,
            'for_percentage' => $forPercentage,
            'against_percentage' => $againstPercentage,
            'abstain_percentage' => $abstainPercentage,
            'is_passing' => bccomp($proposal['votes_for'], $proposal['votes_against']) > 0,
            'quorum_reached' => (bool) $proposal['quorum_reached']
        ];
    }
    
    /**
     * Generate proposal hash for caching
     */
    public static function generateProposalHash(array $targets, array $values, array $calldatas, string $description): string {
        $data = json_encode([
            'targets' => $targets,
            'values' => $values,
            'calldatas' => $calldatas,
            'description' => hash('sha256', $description)
        ]);
        
        return hash('sha256', $data);
    }
    
    /**
     * Validate proposal data
     */
    public static function validateProposalData(array $data): array {
        $errors = [];
        
        // Validate required fields
        if (empty($data['title'])) {
            $errors[] = "Title is required";
        } elseif (strlen($data['title']) > 255) {
            $errors[] = "Title must be less than 255 characters";
        }
        
        if (empty($data['description'])) {
            $errors[] = "Description is required";
        } elseif (strlen($data['description']) > 10000) {
            $errors[] = "Description must be less than 10000 characters";
        }
        
        if (!isset($data['category']) || !self::isValidCategory($data['category'])) {
            $errors[] = "Invalid category";
        }
        
        if (empty($data['targets']) || !is_array($data['targets'])) {
            $errors[] = "Targets array is required";
        } else {
            foreach ($data['targets'] as $target) {
                if (!self::isValidWalletAddress($target)) {
                    $errors[] = "Invalid target address: $target";
                }
            }
        }
        
        if (empty($data['values']) || !is_array($data['values'])) {
            $errors[] = "Values array is required";
        }
        
        if (empty($data['calldatas']) || !is_array($data['calldatas'])) {
            $errors[] = "Calldatas array is required";
        }
        
        // Validate arrays have same length
        if (
            count($data['targets'] ?? []) !== count($data['values'] ?? []) ||
            count($data['targets'] ?? []) !== count($data['calldatas'] ?? [])
        ) {
            $errors[] = "Targets, values, and calldatas arrays must have the same length";
        }
        
        if (!empty($data['wallet_address']) && !self::isValidWalletAddress($data['wallet_address'])) {
            $errors[] = "Invalid wallet address";
        }
        
        return $errors;
    }
    
    /**
     * Validate vote data
     */
    public static function validateVoteData(array $data): array {
        $errors = [];
        
        if (empty($data['proposal_id'])) {
            $errors[] = "Proposal ID is required";
        } elseif (!self::isValidProposalId($data['proposal_id'])) {
            $errors[] = "Invalid proposal ID format";
        }
        
        if (!isset($data['vote_type']) || !self::isValidVoteType($data['vote_type'])) {
            $errors[] = "Invalid vote type (must be 0, 1, or 2)";
        }
        
        if (empty($data['wallet_address']) || !self::isValidWalletAddress($data['wallet_address'])) {
            $errors[] = "Invalid wallet address";
        }
        
        if (isset($data['reason']) && strlen($data['reason']) > 1000) {
            $errors[] = "Vote reason must be less than 1000 characters";
        }
        
        return $errors;
    }
    
    /**
     * Validate delegation data
     */
    public static function validateDelegationData(array $data): array {
        $errors = [];
        
        if (empty($data['wallet_address']) || !self::isValidWalletAddress($data['wallet_address'])) {
            $errors[] = "Invalid delegator wallet address";
        }
        
        if (empty($data['delegatee']) || !self::isValidWalletAddress($data['delegatee'])) {
            $errors[] = "Invalid delegatee wallet address";
        }
        
        if ($data['wallet_address'] === $data['delegatee']) {
            // Self-delegation is OK
        }
        
        return $errors;
    }
    
    /**
     * Format response for API
     */
    public static function formatApiResponse(bool $success, $data = null, string $message = '', array $errors = []): array {
        $response = [
            'success' => $success,
            'timestamp' => date('Y-m-d\TH:i:s\Z')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        return $response;
    }
    
    /**
     * Generate proposal ID from data
     */
    public static function generateProposalId(array $data): string {
        // Generate deterministic hash from proposal data
        $dataString = json_encode([
            'title' => $data['title'],
            'description' => $data['description'],
            'targets' => $data['targets'],
            'values' => $data['values'],
            'calldatas' => $data['calldatas'],
            'proposer' => $data['proposer'],
            'timestamp' => $data['timestamp']
        ]);
        
        return '0x' . hash('sha256', $dataString);
    }
    
    /**
     * Log governance action
     */
    public static function logAction(string $action, int $userId, array $data = []): void {
        error_log(sprintf(
            "[GOVERNANCE] Action: %s | User: %d | Data: %s",
            $action,
            $userId,
            json_encode($data)
        ));
    }
    
    /**
     * Check rate limit
     */
    public static function checkRateLimit(string $ip, string $action, int $maxRequests = 10, int $windowSeconds = 60): bool {
        // Simple in-memory rate limiting (for production, use Redis or database)
        static $requests = [];
        
        $key = $ip . ':' . $action;
        $now = time();
        
        // Clean old requests
        if (isset($requests[$key])) {
            $requests[$key] = array_filter($requests[$key], function($timestamp) use ($now, $windowSeconds) {
                return ($now - $timestamp) < $windowSeconds;
            });
        } else {
            $requests[$key] = [];
        }
        
        // Check if over limit
        if (count($requests[$key]) >= $maxRequests) {
            return false;
        }
        
        // Add current request
        $requests[$key][] = $now;
        
        return true;
    }
    
    /**
     * Decode function calldata (simplified)
     */
    public static function decodeCalldata(string $calldata): array {
        // This is a simplified version
        // In production, use a proper ABI decoder
        
        if (strlen($calldata) < 10) {
            return ['function' => 'unknown', 'args' => []];
        }
        
        // Get function selector (first 4 bytes = 8 hex chars after 0x)
        $selector = substr($calldata, 0, 10);
        
        // Common function selectors (add more as needed)
        $functions = [
            '0xa9059cbb' => 'transfer',
            '0x23b872dd' => 'transferFrom',
            '0x095ea7b3' => 'approve',
            '0x40c10f19' => 'mint',
            '0x42966c68' => 'burn',
            '0x5c19a95c' => 'delegate'
        ];
        
        $functionName = $functions[$selector] ?? 'unknown';
        
        // Get arguments (remaining data)
        $argsData = substr($calldata, 10);
        
        // Parse arguments (simplified - just chunk into 32-byte words)
        $args = [];
        for ($i = 0; $i < strlen($argsData); $i += 64) {
            $arg = substr($argsData, $i, 64);
            if (!empty($arg)) {
                $args[] = '0x' . $arg;
            }
        }
        
        return [
            'function' => $functionName,
            'selector' => $selector,
            'args' => $args
        ];
    }
}
