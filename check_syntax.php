#!/usr/bin/env php
<?php
/**
 * ============================================
 * PHASE 6.5 - SYNTAX CHECKER
 * ============================================
 * Checks syntax of all PHP files without MySQL
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║           FASE 6.5 - PHP SYNTAX VALIDATION                        ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$filesToCheck = [
    // Governance API (existing files)
    'api/governance/get-proposals.php',
    'api/governance/get-proposal.php',
    'api/governance/create-proposal.php',
    'api/governance/cast-vote.php',
    'api/governance/delegate.php',
    'api/governance/stats.php',
    'api/governance/voting-power.php',
    
    // Wallet API (existing files)
    'api/wallet/balances.php',
    'api/wallet/gasless_action.php',
    'api/wallet/gasless_history.php',
    'api/wallet/autoswap_quote.php',
    'api/wallet/autoswap_execute.php',
    'api/wallet/request_address.php',
    'api/wallet/request_withdraw.php',
    'api/wallet/withdraw_history.php',
    'api/wallet/withdraw_limits.php',
    
    // Components
    'components/wallet-button.php',
    'components/network-badge.php',
    'components/navbar.php',
    'components/p2p-toggle.php',
    
    // Pages
    'pages/governance.php',
    'pages/dashboard.php',
    'pages/communities.php',
    
    // Config
    'config/connection.php',
    'config/config.php',
    
    // Assets JS (governance)
    'assets/js/governance/governance-api.js',
    'assets/js/governance/governance-stats.js',
    'assets/js/governance/governance-proposals.js',
    'assets/js/governance/governance-modal.js',
    'assets/js/governance/governance-web3.js',
    
    // Assets JS (web3)
    'assets/js/web3/web3-utils.js',
    'assets/js/web3/web3-connector.js',
    'assets/js/web3/web3-contracts.js',
    'assets/js/web3/web3-signatures.js',
    
    // Assets JS (components)
    'assets/js/components/wallet-button.js',
    'assets/js/components/network-badge.js',
];

$passed = 0;
$failed = 0;
$missing = 0;

echo "Checking " . count($filesToCheck) . " files...\n\n";

foreach ($filesToCheck as $file) {
    $fullPath = __DIR__ . '/' . $file;
    
    // Check if file exists
    if (!file_exists($fullPath)) {
        echo "⚠️  MISSING: $file\n";
        $missing++;
        continue;
    }
    
    // Check syntax
    $output = [];
    $returnCode = 0;
    exec("php -l \"$fullPath\" 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ PASS: $file\n";
        $passed++;
    } else {
        echo "❌ FAIL: $file\n";
        echo "   Error: " . implode("\n   ", $output) . "\n";
        $failed++;
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                         RESULTS SUMMARY                            ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Files:  " . count($filesToCheck) . "\n";
echo "✅ Passed:     $passed\n";
echo "❌ Failed:     $failed\n";
echo "⚠️  Missing:    $missing\n";

$percentage = count($filesToCheck) > 0 ? round(($passed / count($filesToCheck)) * 100, 2) : 0;
echo "\nSuccess Rate: $percentage%\n";

if ($failed === 0 && $missing === 0) {
    echo "\n🎉 ALL SYNTAX CHECKS PASSED!\n\n";
    exit(0);
} else {
    echo "\n⚠️  SOME CHECKS FAILED - Review errors above\n\n";
    exit(1);
}
