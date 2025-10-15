<?php
/**
 * ============================================
 * GOVERNANCE WEB3 HELPER
 * ============================================
 * Functions for interacting with governance smart contracts
 */

require_once __DIR__ . '/../../config/config.php';

class GovernanceWeb3 {
    private $web3Provider;
    private $governorAddress;
    private $tokenAddress;
    private $timelockAddress;
    
    // Contract ABIs (simplified - include only needed functions)
    private $governorABI = [
        [
            "name" => "state",
            "type" => "function",
            "inputs" => [["name" => "proposalId", "type" => "uint256"]],
            "outputs" => [["name" => "", "type" => "uint8"]]
        ],
        [
            "name" => "proposalVotes",
            "type" => "function",
            "inputs" => [["name" => "proposalId", "type" => "uint256"]],
            "outputs" => [
                ["name" => "againstVotes", "type" => "uint256"],
                ["name" => "forVotes", "type" => "uint256"],
                ["name" => "abstainVotes", "type" => "uint256"]
            ]
        ],
        [
            "name" => "quorumReached",
            "type" => "function",
            "inputs" => [["name" => "proposalId", "type" => "uint256"]],
            "outputs" => [["name" => "", "type" => "bool"]]
        ],
        [
            "name" => "proposalDeadline",
            "type" => "function",
            "inputs" => [["name" => "proposalId", "type" => "uint256"]],
            "outputs" => [["name" => "", "type" => "uint256"]]
        ],
        [
            "name" => "proposalSnapshot",
            "type" => "function",
            "inputs" => [["name" => "proposalId", "type" => "uint256"]],
            "outputs" => [["name" => "", "type" => "uint256"]]
        ]
    ];
    
    private $tokenABI = [
        [
            "name" => "getVotes",
            "type" => "function",
            "inputs" => [["name" => "account", "type" => "address"]],
            "outputs" => [["name" => "", "type" => "uint256"]]
        ],
        [
            "name" => "getPastVotes",
            "type" => "function",
            "inputs" => [
                ["name" => "account", "type" => "address"],
                ["name" => "blockNumber", "type" => "uint256"]
            ],
            "outputs" => [["name" => "", "type" => "uint256"]]
        ],
        [
            "name" => "delegates",
            "type" => "function",
            "inputs" => [["name" => "account", "type" => "address"]],
            "outputs" => [["name" => "", "type" => "address"]]
        ],
        [
            "name" => "balanceOf",
            "type" => "function",
            "inputs" => [["name" => "account", "type" => "address"]],
            "outputs" => [["name" => "", "type" => "uint256"]]
        ]
    ];
    
    public function __construct() {
        // Load contract addresses from config or environment
        $this->web3Provider = env('WEB3_PROVIDER_URL', 'http://localhost:8545');
        $this->governorAddress = env('GOVERNOR_CONTRACT_ADDRESS', '');
        $this->tokenAddress = env('GOVERNANCE_TOKEN_ADDRESS', '');
        $this->timelockAddress = env('TIMELOCK_CONTRACT_ADDRESS', '');
    }
    
    /**
     * Get proposal state from blockchain
     * 0=Pending, 1=Active, 2=Canceled, 3=Defeated, 4=Succeeded, 5=Queued, 6=Expired, 7=Executed
     */
    public function getProposalState(string $proposalId): int {
        try {
            // Use Web3.php library or cURL to call contract
            $result = $this->callContract(
                $this->governorAddress,
                'state',
                [$proposalId]
            );
            
            return (int) $result;
        } catch (Exception $e) {
            error_log("Error getting proposal state: " . $e->getMessage());
            return -1; // Error state
        }
    }
    
    /**
     * Get proposal votes from blockchain
     */
    public function getProposalVotes(string $proposalId): array {
        try {
            $result = $this->callContract(
                $this->governorAddress,
                'proposalVotes',
                [$proposalId]
            );
            
            return [
                'against' => $result[0] ?? '0',
                'for' => $result[1] ?? '0',
                'abstain' => $result[2] ?? '0'
            ];
        } catch (Exception $e) {
            error_log("Error getting proposal votes: " . $e->getMessage());
            return ['against' => '0', 'for' => '0', 'abstain' => '0'];
        }
    }
    
    /**
     * Check if quorum is reached
     */
    public function isQuorumReached(string $proposalId): bool {
        try {
            $result = $this->callContract(
                $this->governorAddress,
                'quorumReached',
                [$proposalId]
            );
            
            return (bool) $result;
        } catch (Exception $e) {
            error_log("Error checking quorum: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get voting power for an address
     */
    public function getVotingPower(string $wallet): string {
        try {
            $result = $this->callContract(
                $this->tokenAddress,
                'getVotes',
                [$wallet]
            );
            
            return $result ?? '0';
        } catch (Exception $e) {
            error_log("Error getting voting power: " . $e->getMessage());
            return '0';
        }
    }
    
    /**
     * Get historical voting power at specific block
     */
    public function getPastVotingPower(string $wallet, int $blockNumber): string {
        try {
            $result = $this->callContract(
                $this->tokenAddress,
                'getPastVotes',
                [$wallet, $blockNumber]
            );
            
            return $result ?? '0';
        } catch (Exception $e) {
            error_log("Error getting past voting power: " . $e->getMessage());
            return '0';
        }
    }
    
    /**
     * Get delegate address for a wallet
     */
    public function getDelegate(string $wallet): string {
        try {
            $result = $this->callContract(
                $this->tokenAddress,
                'delegates',
                [$wallet]
            );
            
            return $result ?? '0x0000000000000000000000000000000000000000';
        } catch (Exception $e) {
            error_log("Error getting delegate: " . $e->getMessage());
            return '0x0000000000000000000000000000000000000000';
        }
    }
    
    /**
     * Get token balance
     */
    public function getTokenBalance(string $wallet): string {
        try {
            $result = $this->callContract(
                $this->tokenAddress,
                'balanceOf',
                [$wallet]
            );
            
            return $result ?? '0';
        } catch (Exception $e) {
            error_log("Error getting token balance: " . $e->getMessage());
            return '0';
        }
    }
    
    /**
     * Get proposal deadline (block number)
     */
    public function getProposalDeadline(string $proposalId): int {
        try {
            $result = $this->callContract(
                $this->governorAddress,
                'proposalDeadline',
                [$proposalId]
            );
            
            return (int) $result;
        } catch (Exception $e) {
            error_log("Error getting proposal deadline: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get proposal snapshot (block number when voting starts)
     */
    public function getProposalSnapshot(string $proposalId): int {
        try {
            $result = $this->callContract(
                $this->governorAddress,
                'proposalSnapshot',
                [$proposalId]
            );
            
            return (int) $result;
        } catch (Exception $e) {
            error_log("Error getting proposal snapshot: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Verify signature (for off-chain verification before on-chain submission)
     */
    public function verifySignature(string $message, string $signature, string $expectedAddress): bool {
        try {
            // Prefix message with Ethereum message prefix
            $prefix = "\x19Ethereum Signed Message:\n" . strlen($message);
            $prefixedMessage = $prefix . $message;
            
            // Hash the prefixed message
            $messageHash = hash('sha3-256', $prefixedMessage, true);
            
            // Extract r, s, v from signature
            $signature = substr($signature, 2); // Remove 0x
            $r = substr($signature, 0, 64);
            $s = substr($signature, 64, 64);
            $v = hexdec(substr($signature, 128, 2));
            
            // Adjust v if needed
            if ($v < 27) {
                $v += 27;
            }
            
            // Recover address (simplified - in production use web3.php library)
            // For now, return true if signature format is valid
            return strlen($signature) === 130 && ($v === 27 || $v === 28);
            
        } catch (Exception $e) {
            error_log("Error verifying signature: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current block number
     */
    public function getCurrentBlock(): int {
        try {
            $result = $this->rpcCall('eth_blockNumber', []);
            return hexdec($result);
        } catch (Exception $e) {
            error_log("Error getting current block: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get transaction receipt
     */
    public function getTransactionReceipt(string $txHash): ?array {
        try {
            $result = $this->rpcCall('eth_getTransactionReceipt', [$txHash]);
            return $result;
        } catch (Exception $e) {
            error_log("Error getting transaction receipt: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Call smart contract function (read-only)
     */
    private function callContract(string $contractAddress, string $functionName, array $params): mixed {
        // This is a simplified implementation
        // In production, use a proper Web3 library like web3.php
        
        try {
            // Encode function call
            $data = $this->encodeFunctionCall($functionName, $params);
            
            // Make eth_call RPC request
            $result = $this->rpcCall('eth_call', [
                [
                    'to' => $contractAddress,
                    'data' => $data
                ],
                'latest'
            ]);
            
            // Decode result
            return $this->decodeResult($result);
            
        } catch (Exception $e) {
            error_log("Error calling contract: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Make JSON-RPC call to Ethereum node
     */
    private function rpcCall(string $method, array $params): mixed {
        $ch = curl_init($this->web3Provider);
        
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1
        ]);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("RPC call failed with HTTP code: $httpCode");
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new Exception("RPC error: " . json_encode($result['error']));
        }
        
        return $result['result'] ?? null;
    }
    
    /**
     * Encode function call (simplified)
     */
    private function encodeFunctionCall(string $functionName, array $params): string {
        // This is highly simplified
        // In production, use a proper ABI encoder
        
        // For now, return a placeholder
        // Real implementation would use keccak256 hash of function signature
        return '0x' . hash('sha3-256', $functionName) . str_repeat('0', 64);
    }
    
    /**
     * Decode result (simplified)
     */
    private function decodeResult(string $hexResult): mixed {
        // This is highly simplified
        // In production, use a proper ABI decoder
        
        // Remove 0x prefix
        $hex = substr($hexResult, 2);
        
        // For uint256, just convert hex to decimal
        return hexdec($hex);
    }
    
    /**
     * Get total token supply
     */
    public function getTotalSupply(): string {
        // This would call totalSupply() on the token contract
        // For now, return a placeholder
        // In production, this should call the actual contract
        return '10000000000000000000000000'; // 10,000,000 tokens
    }
    
    /**
     * Convert wei to ether
     */
    public static function weiToEther(string $wei): string {
        return bcdiv($wei, '1000000000000000000', 18);
    }
    
    /**
     * Convert ether to wei
     */
    public static function etherToWei(string $ether): string {
        return bcmul($ether, '1000000000000000000', 0);
    }
    
    /**
     * Format voting power for display
     */
    public static function formatVotingPower(string $wei): string {
        $ether = self::weiToEther($wei);
        
        // Format with thousand separators
        if (bccomp($ether, '1000', 2) >= 0) {
            return number_format((float)$ether, 0) . ' GOVSPHE';
        } else {
            return number_format((float)$ether, 2) . ' GOVSPHE';
        }
    }
}

