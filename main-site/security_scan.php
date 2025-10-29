#!/usr/bin/env php
<?php
/**
 * ============================================
 * SECURITY QUICK SCAN
 * ============================================
 * Basic security checks without MySQL
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║              FASE 6.5 - SECURITY QUICK SCAN                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$securityChecks = [
    'Check 1: Sensitive files protection' => function() {
        $sensitiveFiles = [
            '.env',
            'config/config.php',
            'config/connection.php'
        ];
        
        $issues = [];
        foreach ($sensitiveFiles as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (strpos($content, 'password') !== false && strpos($content, 'root') !== false) {
                    $issues[] = "$file contains default credentials";
                }
            }
        }
        
        return [
            'passed' => empty($issues),
            'message' => empty($issues) ? 'Sensitive files properly configured' : implode(', ', $issues)
        ];
    },
    
    'Check 2: SQL injection protection' => function() {
        $phpFiles = glob('api/**/*.php', GLOB_BRACE);
        $vulnerable = [];
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            // Check for dangerous patterns
            if (preg_match('/\$_(?:GET|POST|REQUEST)\s*\[.*?\].*?mysqli_query/i', $content)) {
                $vulnerable[] = basename($file);
            }
        }
        
        return [
            'passed' => empty($vulnerable),
            'message' => empty($vulnerable) ? 'No obvious SQL injection vulnerabilities' : 'Potential issues in: ' . implode(', ', array_slice($vulnerable, 0, 3))
        ];
    },
    
    'Check 3: XSS protection' => function() {
        $phpFiles = glob('pages/*.php');
        $vulnerable = [];
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            // Check for unescaped output
            if (preg_match('/echo\s+\$_(?:GET|POST|REQUEST)/i', $content)) {
                $vulnerable[] = basename($file);
            }
        }
        
        return [
            'passed' => empty($vulnerable),
            'message' => empty($vulnerable) ? 'XSS protection looks good' : 'Review: ' . implode(', ', $vulnerable)
        ];
    },
    
    'Check 4: Session security' => function() {
        if (file_exists('config/config.php')) {
            $content = file_get_contents('config/config.php');
            $secure = strpos($content, 'session_start()') !== false;
            return [
                'passed' => $secure,
                'message' => $secure ? 'Session management implemented' : 'Session management not found'
            ];
        }
        return ['passed' => false, 'message' => 'Config file not found'];
    },
    
    'Check 5: CORS configuration' => function() {
        $apiFiles = glob('api/**/*.php', GLOB_BRACE);
        $hasCors = false;
        
        foreach (array_slice($apiFiles, 0, 5) as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'Access-Control-Allow-Origin') !== false) {
                $hasCors = true;
                break;
            }
        }
        
        return [
            'passed' => $hasCors,
            'message' => $hasCors ? 'CORS headers configured' : 'CORS headers not found (may be needed)'
        ];
    },
    
    'Check 6: Error handling' => function() {
        if (file_exists('api/error_handler.php')) {
            return ['passed' => true, 'message' => 'Error handler exists'];
        }
        return ['passed' => false, 'message' => 'No centralized error handler'];
    },
    
    'Check 7: Rate limiting' => function() {
        if (file_exists('api/rate_limiter.php')) {
            return ['passed' => true, 'message' => 'Rate limiter implemented'];
        }
        return ['passed' => false, 'message' => 'No rate limiting found'];
    },
    
    'Check 8: Input validation' => function() {
        $apiFiles = glob('api/governance/*.php');
        $validated = 0;
        
        foreach ($apiFiles as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'filter_var') !== false || strpos($content, 'validate') !== false) {
                $validated++;
            }
        }
        
        $percentage = count($apiFiles) > 0 ? round(($validated / count($apiFiles)) * 100) : 0;
        return [
            'passed' => $percentage > 50,
            'message' => "$validated/" . count($apiFiles) . " files use validation ($percentage%)"
        ];
    },
];

$passed = 0;
$failed = 0;

foreach ($securityChecks as $checkName => $checkFunction) {
    $result = $checkFunction();
    
    if ($result['passed']) {
        echo "✅ $checkName\n";
        echo "   " . $result['message'] . "\n\n";
        $passed++;
    } else {
        echo "⚠️  $checkName\n";
        echo "   " . $result['message'] . "\n\n";
        $failed++;
    }
}

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                         SECURITY SUMMARY                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Checks: " . count($securityChecks) . "\n";
echo "✅ Passed:    $passed\n";
echo "⚠️  Failed:    $failed\n";

$score = round(($passed / count($securityChecks)) * 100);
echo "\nSecurity Score: $score/100\n";

if ($score >= 80) {
    echo "Status: ✅ GOOD\n";
} elseif ($score >= 60) {
    echo "Status: ⚠️  FAIR - Needs improvement\n";
} else {
    echo "Status: ❌ POOR - Critical issues\n";
}

echo "\n";
echo "Note: This is a quick scan. Full security audit requires:\n";
echo "  • Manual code review\n";
echo "  • Penetration testing\n";
echo "  • Dependency vulnerability scan\n";
echo "  • Runtime testing with MySQL\n";
echo "\n";
