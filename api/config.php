<?php
/**
 * API Configuration Endpoint
 * 
 * Expone variables de configuración seguras al frontend.
 * NUNCA exponer secrets (API keys, passwords, etc.)
 * 
 * Endpoint: GET /api/config.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/env.php';

use thesocialmask\Config\Env;

// Cargar variables de entorno
Env::load();

try {
    // Configuración del token SPHE
    $spheToken = Env::spheToken();
    
    // Configuración del treasury
    $treasury = Env::treasury();
    
    // Configuraciones de features
    $donations = Env::donations();
    $ppv = Env::payPerView();
    $journalist = Env::journalist();
    $privacy = Env::privacy();
    $moderation = Env::moderation();
    $gunjs = Env::gunjs();
    $ipfs = Env::ipfs();

    // Construir respuesta con solo información segura
    $config = [
        // Token configuration
        'sphe' => [
            'address' => $spheToken['address'],
            'symbol' => $spheToken['symbol'],
            'decimals' => $spheToken['decimals'],
        ],
        
        // Treasury (solo address, no secrets)
        'treasury' => [
            'address' => $treasury['address'],
            'feePercentage' => $treasury['feePercentage'],
        ],
        
        // Donations
        'donations' => [
            'enabled' => $donations['enabled'],
            'minAmount' => $donations['minAmount'],
            'feePercentage' => $donations['feePercentage'],
            'allowAnonymous' => $donations['allowAnonymous'],
            'contractAddress' => $donations['contractAddress'],
        ],
        
        // Pay-Per-View
        'ppv' => [
            'enabled' => $ppv['enabled'],
            'ratePer1000' => $ppv['ratePer1000'],
            'minPayout' => $ppv['minPayout'],
            'schedule' => $ppv['schedule'],
            'bonuses' => $ppv['bonuses'],
            'contractAddress' => $ppv['contractAddress'],
        ],
        
        // Journalist System
        'journalist' => [
            'enabled' => $journalist['enabled'],
            'minReputation' => $journalist['minReputation'],
            'bonusPercentage' => $journalist['bonusPercentage'],
            'allowAnonymous' => $journalist['allowAnonymous'],
            'autoApproveThreshold' => $journalist['autoApproveThreshold'],
            'contractAddress' => $journalist['contractAddress'],
        ],
        
        // Privacy & Encryption
        'privacy' => [
            'e2eEnabled' => $privacy['e2eEnabled'],
            'signalProtocol' => $privacy['signalProtocol'],
            'maxSizeMb' => $privacy['maxSizeMb'],
            'anonymousMode' => $privacy['anonymousMode'],
            'zkpEnabled' => $privacy['zkpEnabled'],
            'polygonIdEnabled' => $privacy['polygonIdEnabled'],
            'torFriendly' => $privacy['torFriendly'],
        ],
        
        // Moderation (solo configuraciones públicas)
        'moderation' => [
            'filterLevel' => $moderation['filterLevel'],
            'reportThreshold' => $moderation['reportThreshold'],
            'rateLimitPerMin' => $moderation['rateLimitPerMin'],
            'verificationRequired' => $moderation['verificationRequired'],
        ],
        
        // Features flags
        'features' => [
            'p2pEnabled' => $gunjs['enabled'],
            'ipfsEnabled' => $ipfs['enabled'],
            'donationsEnabled' => $donations['enabled'],
            'ppvEnabled' => $ppv['enabled'],
            'journalistEnabled' => $journalist['enabled'],
            'e2eEnabled' => $privacy['e2eEnabled'],
            'anonymousMode' => $privacy['anonymousMode'],
        ],
        
        // URLs públicas (sin API keys)
        'services' => [
            'ipfsGateway' => $ipfs['enabled'] ? $ipfs['gateway'] : null,
            'gunjsRelay' => $gunjs['enabled'] ? $gunjs['relayUrl'] : null,
        ],
    ];

    echo json_encode([
        'success' => true,
        'config' => $config,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading configuration',
        'error' => $e->getMessage(),
    ]);
}
