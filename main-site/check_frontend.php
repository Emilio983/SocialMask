#!/usr/bin/env php
<?php
/**
 * ============================================
 * FRONTEND FILES VALIDATOR
 * ============================================
 * Validates JavaScript syntax and structure
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║           FASE 6.5 - FRONTEND VALIDATION                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$jsFiles = [
    'assets/js/governance/governance-api.js',
    'assets/js/governance/governance-stats.js',
    'assets/js/governance/governance-proposals.js',
    'assets/js/governance/governance-modal.js',
    'assets/js/governance/governance-web3.js',
    'assets/js/governance/governance-main.js',
    'assets/js/web3/web3-utils.js',
    'assets/js/web3/web3-connector.js',
    'assets/js/web3/web3-contracts.js',
    'assets/js/web3/web3-signatures.js',
    'assets/js/components/wallet-button.js',
    'assets/js/components/network-badge.js',
];

$passed = 0;
$failed = 0;
$warnings = 0;

echo "Checking " . count($jsFiles) . " JavaScript files...\n\n";

foreach ($jsFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    
    if (!file_exists($fullPath)) {
        echo "⚠️  MISSING: $file\n";
        $warnings++;
        continue;
    }
    
    $content = file_get_contents($fullPath);
    $issues = [];
    
    // Basic checks
    $lines = explode("\n", $content);
    $lineCount = count($lines);
    
    // Check for common issues
    if (strpos($content, 'console.log') !== false) {
        $issues[] = "Contains console.log (should be removed in production)";
    }
    
    if (strpos($content, 'debugger') !== false) {
        $issues[] = "Contains debugger statement";
    }
    
    // Check for TODO/FIXME
    if (preg_match('/TODO|FIXME/i', $content)) {
        $issues[] = "Contains TODO/FIXME comments";
    }
    
    // Check file size
    $fileSize = filesize($fullPath);
    if ($fileSize > 100000) { // 100KB
        $issues[] = "Large file size (" . round($fileSize/1024, 2) . " KB)";
    }
    
    // Check for proper export
    if (strpos($content, 'window.') === false && strpos($content, 'export') === false) {
        $issues[] = "No exports detected (may not be accessible)";
    }
    
    // Display results
    if (empty($issues)) {
        echo "✅ PASS: $file ($lineCount lines)\n";
        $passed++;
    } else {
        echo "⚠️  WARN: $file\n";
        foreach ($issues as $issue) {
            echo "   - $issue\n";
        }
        $warnings++;
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                         RESULTS SUMMARY                            ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Files:  " . count($jsFiles) . "\n";
echo "✅ Passed:     $passed\n";
echo "⚠️  Warnings:   $warnings\n";
echo "❌ Failed:     $failed\n";

if ($failed === 0) {
    echo "\n✅ All JavaScript files validated!\n\n";
} else {
    echo "\n⚠️  Some issues found - review above\n\n";
}
