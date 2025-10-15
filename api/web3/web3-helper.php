<?php
/**
 * ============================================
 * WEB3 PHP HELPER
 * ============================================
 * Utility functions for Web3 operations in PHP
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

/**
 * Validate Ethereum address format
 * 
 * @param string $address Address to validate
 * @return bool True if valid
 */
function isValidEthAddress($address) {
    return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
}

/**
 * Normalize Ethereum address to checksum format
 * 
 * @param string $address Address to normalize
 * @return string Checksummed address
 */
function toChecksumAddress($address) {
    $address = strtolower(str_replace('0x', '', $address));
    $hash = hash('sha3-256', $address);
    $checksum = '0x';
    
    for ($i = 0; $i < strlen($address); $i++) {
        if (hexdec($hash[$i]) > 7) {
            $checksum .= strtoupper($address[$i]);
        } else {
            $checksum .= $address[$i];
        }
    }
    
    return $checksum;
}

/**
 * Convert Wei to Ether
 * 
 * @param string $wei Amount in Wei
 * @param int $decimals Token decimals (default 18)
 * @return string Amount in Ether
 */
function weiToEther($wei, $decimals = 18) {
    $divisor = bcpow('10', (string)$decimals);
    return bcdiv($wei, $divisor, $decimals);
}

/**
 * Convert Ether to Wei
 * 
 * @param string $ether Amount in Ether
 * @param int $decimals Token decimals (default 18)
 * @return string Amount in Wei
 */
function etherToWei($ether, $decimals = 18) {
    $multiplier = bcpow('10', (string)$decimals);
    return bcmul($ether, $multiplier, 0);
}

/**
 * Encode function call data
 * 
 * @param string $functionSignature Function signature (e.g., "balanceOf(address)")
 * @param array $params Parameters
 * @return string Encoded data
 */
function encodeFunctionCall($functionSignature, $params = []) {
    // Get function selector (first 4 bytes of keccak256 hash)
    $selector = substr(hash('sha3-256', $functionSignature), 0, 8);
    
    // Encode parameters
    $encodedParams = '';
    foreach ($params as $param) {
        if (is_string($param) && isValidEthAddress($param)) {
            // Encode address: pad to 32 bytes
            $encodedParams .= str_pad(substr($param, 2), 64, '0', STR_PAD_LEFT);
        } elseif (is_numeric($param)) {
            // Encode number: convert to hex and pad to 32 bytes
            $hex = dechex($param);
            $encodedParams .= str_pad($hex, 64, '0', STR_PAD_LEFT);
        }
        // Add more type encodings as needed
    }
    
    return '0x' . $selector . $encodedParams;
}

/**
 * Decode hex string to number
 * 
 * @param string $hex Hex string
 * @return string Decimal number
 */
function hexToDec($hex) {
    $hex = str_replace('0x', '', $hex);
    return (string)hexdec($hex);
}

/**
 * Get contract addresses based on chain ID
 * 
 * @param string $chainId Chain ID (e.g., "0x89")
 * @return array Contract addresses
 */
function getContractAddresses($chainId) {
    $addresses = [
        '0x89' => [ // Polygon Mainnet
            'governor' => '0x0000000000000000000000000000000000000000', // TODO: Update
            'token' => '0x0000000000000000000000000000000000000000',
            'timelock' => '0x0000000000000000000000000000000000000000'
        ],
        '0x13882' => [ // Amoy Testnet
            'governor' => '0x0000000000000000000000000000000000000000', // TODO: Update
            'token' => '0x0000000000000000000000000000000000000000',
            'timelock' => '0x0000000000000000000000000000000000000000'
        ]
    ];
    
    return $addresses[$chainId] ?? $addresses['0x89'];
}

/**
 * Get RPC URL for chain
 * 
 * @param string $chainId Chain ID
 * @return string RPC URL
 */
function getRpcUrlForChain($chainId) {
    $urls = [
        '0x89' => 'https://polygon-rpc.com',
        '0x13882' => 'https://rpc-amoy.polygon.technology',
        '0x1' => 'https://eth.llamarpc.com'
    ];
    
    return $urls[$chainId] ?? $urls['0x89'];
}

/**
 * Get chain name from ID
 * 
 * @param string $chainId Chain ID
 * @return string Chain name
 */
function getChainName($chainId) {
    $names = [
        '0x89' => 'Polygon',
        '0x13882' => 'Amoy Testnet',
        '0x1' => 'Ethereum',
        '0x38' => 'BSC'
    ];
    
    return $names[$chainId] ?? 'Unknown';
}

/**
 * Get block explorer URL
 * 
 * @param string $chainId Chain ID
 * @param string $address Address or tx hash
 * @param string $type Type: 'address' or 'tx'
 * @return string Explorer URL
 */
function getExplorerUrl($chainId, $address, $type = 'address') {
    $explorers = [
        '0x89' => 'https://polygonscan.com',
        '0x13882' => 'https://amoy.polygonscan.com',
        '0x1' => 'https://etherscan.io'
    ];
    
    $baseUrl = $explorers[$chainId] ?? $explorers['0x89'];
    
    return $baseUrl . '/' . $type . '/' . $address;
}

/**
 * Make RPC call to blockchain
 * 
 * @param string $rpcUrl RPC endpoint URL
 * @param string $method JSON-RPC method
 * @param array $params Method parameters
 * @return mixed Response data
 */
function makeRpcCall($rpcUrl, $method, $params = []) {
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => $params
    ];
    
    $ch = curl_init($rpcUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('RPC call failed with HTTP code: ' . $httpCode);
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        throw new Exception('RPC error: ' . $data['error']['message']);
    }
    
    return $data['result'] ?? null;
}

/**
 * Call contract method (read-only)
 * 
 * @param string $contractAddress Contract address
 * @param string $functionSignature Function signature
 * @param array $params Parameters
 * @param string $chainId Chain ID
 * @return mixed Result
 */
function callContractMethod($contractAddress, $functionSignature, $params, $chainId) {
    $rpcUrl = getRpcUrlForChain($chainId);
    $data = encodeFunctionCall($functionSignature, $params);
    
    $result = makeRpcCall($rpcUrl, 'eth_call', [
        [
            'to' => $contractAddress,
            'data' => $data
        ],
        'latest'
    ]);
    
    return $result;
}

/**
 * Get token balance
 * 
 * @param string $tokenAddress Token contract address
 * @param string $walletAddress Wallet address
 * @param string $chainId Chain ID
 * @return string Balance in Wei
 */
function getTokenBalance($tokenAddress, $walletAddress, $chainId) {
    try {
        $result = callContractMethod(
            $tokenAddress,
            'balanceOf(address)',
            [$walletAddress],
            $chainId
        );
        
        return hexToDec($result);
    } catch (Exception $e) {
        error_log('[getTokenBalance Error] ' . $e->getMessage());
        return '0';
    }
}

/**
 * Get voting power
 * 
 * @param string $tokenAddress Token contract address
 * @param string $walletAddress Wallet address
 * @param string $chainId Chain ID
 * @return string Voting power in Wei
 */
function getVotingPower($tokenAddress, $walletAddress, $chainId) {
    try {
        $result = callContractMethod(
            $tokenAddress,
            'getVotes(address)',
            [$walletAddress],
            $chainId
        );
        
        return hexToDec($result);
    } catch (Exception $e) {
        error_log('[getVotingPower Error] ' . $e->getMessage());
        return '0';
    }
}

/**
 * Get delegatee
 * 
 * @param string $tokenAddress Token contract address
 * @param string $walletAddress Wallet address
 * @param string $chainId Chain ID
 * @return string Delegatee address
 */
function getDelegatee($tokenAddress, $walletAddress, $chainId) {
    try {
        $result = callContractMethod(
            $tokenAddress,
            'delegates(address)',
            [$walletAddress],
            $chainId
        );
        
        // Result is 32-byte hex, extract last 20 bytes for address
        return '0x' . substr($result, -40);
    } catch (Exception $e) {
        error_log('[getDelegatee Error] ' . $e->getMessage());
        return '0x0000000000000000000000000000000000000000';
    }
}
