<?php

/**
 * API Endpoint - Get Active Token Configuration
 * 
 * Devuelve la configuración del token activo para el sistema de donaciones.
 * El token activo se define por DONATIONS_ACTIVE_TOKEN en .env (sphe | polygon | custom)
 * 
 * Endpoint: /api/donations/token-config.php
 * Method: GET
 * Auth: No requerida (información pública)
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "token": {
 *       "address": "0x059cf...",
 *       "symbol": "SPHE",
 *       "decimals": 18,
 *       "name": "TheSocialMask Token",
 *       "minDonation": 1.0,
 *       "blockchain": "polygon",
 *       "chainId": 137,
 *       "rpcUrl": "https://polygon-rpc.com",
 *       "explorerUrl": "https://polygonscan.com"
 *     },
 *     "donationSettings": {
 *       "enabled": true,
 *       "feePercentage": 2.5,
 *       "allowAnonymous": true,
 *       "trackInDb": true,
 *       "trackInGunjs": true
 *     },
 *     "validation": {
 *       "valid": true,
 *       "errors": []
 *     }
 *   }
 * }
 * 
 * @package API\Donations
 * @version 1.0.0
 * @since FASE 3.1
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
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET.'
    ]);
    exit;
}

require_once __DIR__ . '/../../config/TokenManager.php';
require_once __DIR__ . '/../../config/env.php';

use TheSocialMask\Config\Env;

try {
    // Initialize TokenManager
    $tokenManager = new TokenManager();

    // Get active token configuration
    $activeToken = $tokenManager->getActiveToken();

    // Get donation settings
    $donationSettings = Env::donations();

    // Validate configuration
    $validation = $tokenManager->validateActiveToken();

    // Get token stats
    $stats = $tokenManager->getTokenStats();

    // Response
    $response = [
        'success' => true,
        'data' => [
            'token' => $activeToken,
            'donationSettings' => [
                'enabled' => $donationSettings['enabled'],
                'feePercentage' => $donationSettings['feePercentage'],
                'contractAddress' => $donationSettings['contractAddress'],
                'allowAnonymous' => $donationSettings['allowAnonymous'],
                'trackInDb' => $donationSettings['trackInDb'],
                'trackInGunjs' => $donationSettings['trackInGunjs'],
                'leaderboardEnabled' => $donationSettings['leaderboardEnabled'],
            ],
            'validation' => $validation,
            'stats' => $stats,
        ],
        'timestamp' => time(),
    ];

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load token configuration',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
