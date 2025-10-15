<?php
/**
 * FASE 6.5 - Opción C: Fix Issues Script
 * 
 * Este script automatiza las correcciones encontradas en el testing:
 * 1. Análisis de console.log statements
 * 2. Verificación de session management
 * 3. Análisis de input validation coverage
 */

// Colors para output
define('RED', "\033[31m");
define('GREEN', "\033[32m");
define('YELLOW', "\033[33m");
define('CYAN', "\033[36m");
define('RESET', "\033[0m");

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║            FASE 6.5 - OPCIÓN C: FIX ISSUES ANALYSIS             ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Issue 1: Console.log Analysis
echo YELLOW . "🔍 ISSUE 1: Console.log Statements Analysis\n" . RESET;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$jsFiles = [
    'assets/js/governance/governance-web3.js',
    'assets/js/governance/governance-main.js',
    'assets/js/web3/web3-utils.js',
    'assets/js/web3/web3-connector.js',
    'assets/js/web3/web3-contracts.js',
    'assets/js/web3/web3-signatures.js',
    'assets/js/components/wallet-button.js',
    'assets/js/components/network-badge.js'
];

$totalConsoleLog = 0;
$fileStats = [];

foreach ($jsFiles as $file) {
    if (!file_exists($file)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $count = substr_count($content, 'console.log');
    $totalConsoleLog += $count;
    
    if ($count > 0) {
        $fileStats[] = ['file' => $file, 'count' => $count];
        echo "⚠️  " . basename($file) . ": $count console.log statements\n";
    }
}

echo "\n📊 Total console.log statements: $totalConsoleLog\n";
echo "📝 Recommendation: Replace with proper logging or remove for production\n\n";

// Issue 2: Session Management Check
echo YELLOW . "🔍 ISSUE 2: Session Management Verification\n" . RESET;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$configFiles = [
    'config/config.php',
    'config/connection.php',
    'api/check_session.php',
    'api/utils.php'
];

$sessionFound = false;
$sessionFiles = [];

foreach ($configFiles as $file) {
    if (!file_exists($file)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    if (preg_match('/session_start\s*\(/', $content)) {
        $sessionFound = true;
        $sessionFiles[] = $file;
        echo GREEN . "✅ Session management found in: $file\n" . RESET;
    }
}

if ($sessionFound) {
    echo GREEN . "\n✅ Session management is configured\n" . RESET;
    echo "📝 Files with session handling: " . count($sessionFiles) . "\n";
} else {
    echo RED . "\n❌ Session management NOT found in config files\n" . RESET;
    echo "📝 Recommendation: Add session_start() to config/config.php\n";
}

echo "\n";

// Issue 3: Input Validation Analysis
echo YELLOW . "🔍 ISSUE 3: Input Validation Coverage Analysis\n" . RESET;
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$governanceApis = [
    'api/governance/get-proposals.php',
    'api/governance/get-proposal.php',
    'api/governance/create-proposal.php',
    'api/governance/cast-vote.php',
    'api/governance/delegate.php',
    'api/governance/stats.php',
    'api/governance/voting-power.php'
];

$validationPatterns = [
    'filter_input',
    'filter_var',
    'FILTER_VALIDATE',
    'FILTER_SANITIZE',
    'preg_match',
    'is_numeric',
    'is_string',
    'trim(',
    'strip_tags',
    'htmlspecialchars'
];

$filesWithValidation = [];
$filesWithoutValidation = [];

foreach ($governanceApis as $file) {
    if (!file_exists($file)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $hasValidation = false;
    
    foreach ($validationPatterns as $pattern) {
        if (stripos($content, $pattern) !== false) {
            $hasValidation = true;
            break;
        }
    }
    
    if ($hasValidation) {
        $filesWithValidation[] = basename($file);
        echo GREEN . "✅ " . basename($file) . " - Has validation\n" . RESET;
    } else {
        $filesWithoutValidation[] = basename($file);
        echo RED . "❌ " . basename($file) . " - NO validation\n" . RESET;
    }
}

$validationCoverage = count($filesWithValidation) / count($governanceApis) * 100;

echo "\n📊 Validation Coverage: " . round($validationCoverage) . "%\n";
echo "✅ Files with validation: " . count($filesWithValidation) . "\n";
echo "❌ Files without validation: " . count($filesWithoutValidation) . "\n";

if (count($filesWithoutValidation) > 0) {
    echo "\n📝 Files that need validation:\n";
    foreach ($filesWithoutValidation as $file) {
        echo "   • $file\n";
    }
}

// Summary
echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                         FIX SUMMARY                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

echo "📋 ISSUES FOUND:\n\n";
echo "1. Console.log: $totalConsoleLog statements in " . count($fileStats) . " files\n";
echo "   Priority: MEDIUM\n";
echo "   Estimated fix time: 30 minutes\n\n";

echo "2. Session Management: " . ($sessionFound ? GREEN . "✅ FOUND" . RESET : RED . "❌ NOT FOUND" . RESET) . "\n";
echo "   Priority: " . ($sessionFound ? "OK" : "HIGH") . "\n";
echo "   Estimated fix time: " . ($sessionFound ? "0 minutes" : "1 hour") . "\n\n";

echo "3. Input Validation: " . round($validationCoverage) . "% coverage\n";
echo "   Priority: " . ($validationCoverage >= 80 ? "LOW" : "MEDIUM") . "\n";
echo "   Estimated fix time: " . (count($filesWithoutValidation) * 30) . " minutes\n\n";

$totalFixTime = 30 + ($sessionFound ? 0 : 60) + (count($filesWithoutValidation) * 30);
echo "⏱️  Total estimated fix time: " . round($totalFixTime / 60, 1) . " hours\n\n";

echo GREEN . "✅ Analysis complete! Check fix_recommendations.md for detailed fixes\n" . RESET;
