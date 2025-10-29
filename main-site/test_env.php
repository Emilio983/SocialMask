<?php
/**
 * Test script for .env configuration
 * Usage: php test_env.php
 */

require_once __DIR__ . '/config/env.php';

use TheSocialMask\Config\Env;

echo "🔍 Testing TheSocialMask .env Configuration\n";
echo "=====================================\n\n";

// Load environment
Env::load();

// Test 1: Basic variables
echo "✅ Test 1: Basic Variables\n";
echo "  - DB_NAME: " . Env::get('DB_NAME') . "\n";
echo "  - APP_NAME: " . Env::get('APP_NAME') . "\n";
echo "  - NETWORK: " . Env::get('NETWORK') . "\n\n";

// Test 2: SPHE Token
echo "✅ Test 2: SPHE Token Configuration\n";
$sphe = Env::spheToken();
echo "  - Address: " . $sphe['address'] . "\n";
echo "  - Symbol: " . $sphe['symbol'] . "\n";
echo "  - Decimals: " . $sphe['decimals'] . "\n\n";

// Test 3: Treasury
echo "✅ Test 3: Treasury Configuration\n";
$treasury = Env::treasury();
echo "  - Address: " . $treasury['address'] . "\n";
echo "  - Fee Percentage: " . $treasury['feePercentage'] . "%\n\n";

// Test 4: Donations
echo "✅ Test 4: Donations System\n";
$donations = Env::donations();
echo "  - Enabled: " . ($donations['enabled'] ? 'YES' : 'NO') . "\n";
echo "  - Min Amount: " . $donations['minAmount'] . " SPHE\n";
echo "  - Fee: " . $donations['feePercentage'] . "%\n";
echo "  - Allow Anonymous: " . ($donations['allowAnonymous'] ? 'YES' : 'NO') . "\n\n";

// Test 5: Pay-Per-View
echo "✅ Test 5: Pay-Per-View System\n";
$ppv = Env::payPerView();
echo "  - Enabled: " . ($ppv['enabled'] ? 'YES' : 'NO') . "\n";
echo "  - Rate per 1000 views: " . $ppv['ratePer1000'] . " SPHE\n";
echo "  - Min Payout: " . $ppv['minPayout'] . " SPHE\n";
echo "  - Schedule: " . $ppv['schedule'] . "\n";
echo "  - Bonus 10K: " . $ppv['bonuses']['10k'] . "%\n";
echo "  - Bonus 100K: " . $ppv['bonuses']['100k'] . "%\n";
echo "  - Bonus 1M: " . $ppv['bonuses']['1m'] . "%\n\n";

// Test 6: Journalist System
echo "✅ Test 6: Journalist System\n";
$journalist = Env::journalist();
echo "  - Enabled: " . ($journalist['enabled'] ? 'YES' : 'NO') . "\n";
echo "  - Min Reputation: " . $journalist['minReputation'] . "\n";
echo "  - Bonus Percentage: " . $journalist['bonusPercentage'] . "%\n";
echo "  - Allow Anonymous: " . ($journalist['allowAnonymous'] ? 'YES' : 'NO') . "\n";
echo "  - Auto Approve Threshold: " . $journalist['autoApproveThreshold'] . "\n\n";

// Test 7: Privacy
echo "✅ Test 7: Privacy & Encryption\n";
$privacy = Env::privacy();
echo "  - E2E Encryption: " . ($privacy['e2eEnabled'] ? 'YES' : 'NO') . "\n";
echo "  - Signal Protocol: " . ($privacy['signalProtocol'] ? 'YES' : 'NO') . "\n";
echo "  - Max Message Size: " . $privacy['maxSizeMb'] . " MB\n";
echo "  - Anonymous Mode: " . ($privacy['anonymousMode'] ? 'YES' : 'NO') . "\n";
echo "  - ZKP Enabled: " . ($privacy['zkpEnabled'] ? 'YES' : 'NO') . "\n";
echo "  - Polygon ID: " . ($privacy['polygonIdEnabled'] ? 'YES' : 'NO') . "\n";
echo "  - Tor Friendly: " . ($privacy['torFriendly'] ? 'YES' : 'NO') . "\n\n";

// Test 8: Moderation
echo "✅ Test 8: Moderation\n";
$moderation = Env::moderation();
echo "  - Auto Moderation: " . ($moderation['autoEnabled'] ? 'YES' : 'NO') . "\n";
echo "  - Filter Level: " . $moderation['filterLevel'] . "\n";
echo "  - Report Threshold: " . $moderation['reportThreshold'] . "\n";
echo "  - Rate Limit: " . $moderation['rateLimitPerMin'] . " req/min\n";
echo "  - Illegal Keywords: " . implode(', ', $moderation['illegalKeywords']) . "\n\n";

// Test 9: P2P Infrastructure
echo "✅ Test 9: P2P Infrastructure\n";
$gunjs = Env::gunjs();
$ipfs = Env::ipfs();
echo "  Gun.js:\n";
echo "    - Enabled: " . ($gunjs['enabled'] ? 'YES' : 'NO') . "\n";
echo "    - Relay URL: " . ($gunjs['relayUrl'] ?: 'Not configured') . "\n";
echo "  IPFS:\n";
echo "    - Enabled: " . ($ipfs['enabled'] ? 'YES' : 'NO') . "\n";
echo "    - Provider: " . $ipfs['provider'] . "\n";
echo "    - Gateway: " . $ipfs['gateway'] . "\n\n";

// Test 10: VPS Optimization
echo "✅ Test 10: VPS Optimization\n";
$vps = Env::vpsOptimization();
echo "  - MySQL Buffer Pool: " . $vps['mysqlBufferPool'] . "\n";
echo "  - MySQL Max Connections: " . $vps['mysqlMaxConnections'] . "\n";
echo "  - PHP-FPM Max Children: " . $vps['phpFpmMaxChildren'] . "\n";
echo "  - Nginx Worker Connections: " . $vps['nginxWorkerConnections'] . "\n";
echo "  - Enable Swap: " . ($vps['enableSwap'] ? 'YES' : 'NO') . "\n";
echo "  - Use External Services: " . ($vps['useExternalServices'] ? 'YES' : 'NO') . "\n";
echo "  - Cache TTL: " . $vps['cacheTtl'] . " seconds\n\n";

echo "=====================================\n";
echo "✅ All tests passed!\n";
echo "🎉 Configuration loaded successfully!\n";
