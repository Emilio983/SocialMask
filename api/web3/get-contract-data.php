<?php
/**
 * ============================================
 * GET CONTRACT DATA ENDPOINT
 * ============================================
 * Reads data from blockchain smart contracts with caching
 * 
 * Method: GET
 * Params: ?type=balance&address=0x...&chainId=0x89
 * Output: {success, data, cached, timestamp}
 * 
 * Supported types:
 * - balance: Token balance
 * - voting_power: Voting power (delegated votes)
 * - delegates: Current delegatee
 * - proposal_state: Proposal state
 * - proposal_votes: Proposal vote counts
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/rate_limiter.php';

// Rate limiting
$rateLimiter = new RateLimiter();
if (!$rateLimiter->checkLimit('contract_data', 30, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many requests. Please try again later.'
    ]);
    exit;
}

// Get parameters
$type = $_GET['type'] ?? null;
$address = $_GET['address'] ?? null;
$chainId = $_GET['chainId'] ?? '0x89'; // Default to Polygon
$proposalId = $_GET['proposalId'] ?? null;

// Validate type
$validTypes = ['balance', 'voting_power', 'delegates', 'proposal_state', 'proposal_votes'];
if (!in_array($type, $validTypes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid type. Must be one of: ' . implode(', ', $validTypes)
    ]);
    exit;
}

// Validate address for address-specific queries
if (in_array($type, ['balance', 'voting_power', 'delegates']) && !$address) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Address parameter is required for this type'
    ]);
    exit;
}

// Validate proposal ID for proposal-specific queries
if (in_array($type, ['proposal_state', 'proposal_votes']) && !$proposalId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'proposalId parameter is required for this type'
    ]);
    exit;
}

// Validate address format if provided
if ($address && !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid address format'
    ]);
    exit;
}

try {
    // Check cache first
    $cacheKey = getCacheKey($type, $address, $chainId, $proposalId);
    $cachedData = getFromCache($cacheKey);
    
    if ($cachedData !== null) {
        echo json_encode([
            'success' => true,
            'data' => $cachedData['data'],
            'cached' => true,
            'timestamp' => $cachedData['timestamp'],
            'chainId' => $chainId
        ]);
        exit;
    }
    
    // Fetch fresh data from blockchain
    $data = fetchContractData($type, $address, $chainId, $proposalId);
    
    if ($data === null) {
        throw new Exception('Failed to fetch contract data');
    }
    
    // Cache the result
    saveToCache($cacheKey, $data);
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'cached' => false,
        'timestamp' => time(),
        'chainId' => $chainId
    ]);
    
} catch (Exception $e) {
    error_log('[Get Contract Data Error] ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch contract data: ' . $e->getMessage()
    ]);
}

/**
 * Fetch data from smart contract
 * 
 * @param string $type Data type to fetch
 * @param string $address Wallet address
 * @param string $chainId Chain ID
 * @param string $proposalId Proposal ID
 * @return mixed Contract data
 */
function fetchContractData($type, $address, $chainId, $proposalId) {
    try {
        switch ($type) {
            case 'balance':
                return fetchTokenBalance($address, $chainId);
            
            case 'voting_power':
                return fetchVotingPower($address, $chainId);
            
            case 'delegates':
                return fetchDelegates($address, $chainId);
            
            case 'proposal_state':
                return fetchProposalState($proposalId, $chainId);
            
            case 'proposal_votes':
                return fetchProposalVotes($proposalId, $chainId);
            
            default:
                throw new Exception('Unknown data type');
        }
    } catch (Exception $e) {
        error_log('[fetchContractData Error] ' . $e->getMessage());
        return null;
    }
}

/**
 * Fetch token balance from blockchain
 * 
 * @param string $address Wallet address
 * @param string $chainId Chain ID
 * @return array Balance data
 */
function fetchTokenBalance($address, $chainId) {
    // This is a placeholder implementation
    // In production, use Web3.php to call balanceOf(address)
    
    // Example with Web3.php:
    // $contract = new Contract(RPC_URL, GOV_TOKEN_ABI);
    // $balance = $contract->at(GOV_TOKEN_ADDRESS)->call('balanceOf', $address);
    
    // For now, return placeholder
    return [
        'address' => $address,
        'balance' => '0',
        'decimals' => 18,
        'symbol' => 'GOV'
    ];
}

/**
 * Fetch voting power from blockchain
 * 
 * @param string $address Wallet address
 * @param string $chainId Chain ID
 * @return array Voting power data
 */
function fetchVotingPower($address, $chainId) {
    // Placeholder - use Web3.php to call getVotes(address)
    
    return [
        'address' => $address,
        'votes' => '0',
        'decimals' => 18
    ];
}

/**
 * Fetch delegatee from blockchain
 * 
 * @param string $address Wallet address
 * @param string $chainId Chain ID
 * @return array Delegate data
 */
function fetchDelegates($address, $chainId) {
    // Placeholder - use Web3.php to call delegates(address)
    
    return [
        'address' => $address,
        'delegatee' => '0x0000000000000000000000000000000000000000'
    ];
}

/**
 * Fetch proposal state from blockchain
 * 
 * @param string $proposalId Proposal ID
 * @param string $chainId Chain ID
 * @return array Proposal state data
 */
function fetchProposalState($proposalId, $chainId) {
    // Placeholder - use Web3.php to call state(proposalId)
    
    $states = [
        0 => 'Pending',
        1 => 'Active',
        2 => 'Canceled',
        3 => 'Defeated',
        4 => 'Succeeded',
        5 => 'Queued',
        6 => 'Expired',
        7 => 'Executed'
    ];
    
    return [
        'proposalId' => $proposalId,
        'state' => 0,
        'stateName' => $states[0]
    ];
}

/**
 * Fetch proposal votes from blockchain
 * 
 * @param string $proposalId Proposal ID
 * @param string $chainId Chain ID
 * @return array Vote counts
 */
function fetchProposalVotes($proposalId, $chainId) {
    // Placeholder - use Web3.php to call proposalVotes(proposalId)
    
    return [
        'proposalId' => $proposalId,
        'forVotes' => '0',
        'againstVotes' => '0',
        'abstainVotes' => '0'
    ];
}

/**
 * Generate cache key
 * 
 * @param string $type Data type
 * @param string $address Address
 * @param string $chainId Chain ID
 * @param string $proposalId Proposal ID
 * @return string Cache key
 */
function getCacheKey($type, $address, $chainId, $proposalId) {
    $parts = [
        'contract_data',
        $type,
        $chainId,
        $address ?? 'no_addr',
        $proposalId ?? 'no_prop'
    ];
    
    return implode('_', $parts);
}

/**
 * Get data from cache
 * 
 * @param string $key Cache key
 * @return array|null Cached data or null
 */
function getFromCache($key) {
    $cacheDir = __DIR__ . '/../../cache/web3';
    $cacheFile = $cacheDir . '/' . md5($key) . '.json';
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    $data = json_decode(file_get_contents($cacheFile), true);
    
    if (!$data) {
        return null;
    }
    
    // Check if cache is still valid (5 minutes)
    $age = time() - $data['timestamp'];
    if ($age > 300) {
        unlink($cacheFile);
        return null;
    }
    
    return $data;
}

/**
 * Save data to cache
 * 
 * @param string $key Cache key
 * @param mixed $data Data to cache
 */
function saveToCache($key, $data) {
    try {
        $cacheDir = __DIR__ . '/../../cache/web3';
        
        // Create cache directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        
        $cacheFile = $cacheDir . '/' . md5($key) . '.json';
        
        $cacheData = [
            'data' => $data,
            'timestamp' => time()
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData));
        
    } catch (Exception $e) {
        error_log('[saveToCache Error] ' . $e->getMessage());
        // Don't throw - caching failure shouldn't break the main flow
    }
}

/**
 * Get RPC URL for chain
 * 
 * @param string $chainId Chain ID
 * @return string RPC URL
 */
function getRpcUrl($chainId) {
    $rpcUrls = [
        '0x89' => 'https://polygon-rpc.com', // Polygon Mainnet
        '0x13882' => 'https://rpc-amoy.polygon.technology', // Amoy Testnet
        '0x1' => 'https://eth.llamarpc.com' // Ethereum Mainnet
    ];
    
    return $rpcUrls[$chainId] ?? $rpcUrls['0x89'];
}
