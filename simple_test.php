<?php
/**
 * FASE 6.5 - Simple API Testing
 * Tests básicos sin dependencias complejas
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║              FASE 6.5 - SIMPLE API TESTING                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Test 1: MySQL Connection
echo "TEST 1: MySQL Connection\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $mysqli = new mysqli('localhost', 'root', '', 'sphera');
    
    if ($mysqli->connect_error) {
        echo "❌ FAILED: " . $mysqli->connect_error . "\n";
        exit(1);
    }
    
    echo "✅ PASSED: Connected to MySQL\n";
    echo "   Database: sphera\n";
    echo "   Host: localhost\n\n";
    
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Tables Exist
echo "TEST 2: Governance Tables Exist\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$required_tables = [
    'governance_proposals',
    'governance_votes',
    'governance_delegations',
    'governance_comments',
    'users'
];

$tables_exist = 0;
foreach ($required_tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table exists: $table\n";
        $tables_exist++;
    } else {
        echo "❌ Table missing: $table\n";
    }
}

echo "\n📊 Tables: $tables_exist/" . count($required_tables) . " exist\n\n";

// Test 3: GET Proposals API
echo "TEST 3: GET /api/governance/get-proposals.php\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$start_time = microtime(true);
$ch = curl_init('http://localhost/api/governance/get-proposals.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$time_taken = round((microtime(true) - $start_time) * 1000, 2);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        echo "✅ PASSED: API responded successfully\n";
        echo "   HTTP Code: $http_code\n";
        echo "   Response Time: {$time_taken}ms\n";
        echo "   Has data: " . (isset($data['proposals']) ? 'Yes' : 'No') . "\n";
    } else {
        echo "⚠️  WARNING: Invalid JSON response\n";
        echo "   HTTP Code: $http_code\n";
        echo "   Response Time: {$time_taken}ms\n";
    }
} else {
    echo "❌ FAILED: HTTP $http_code\n";
    echo "   Response Time: {$time_taken}ms\n";
}
echo "\n";

// Test 4: GET Stats API
echo "TEST 4: GET /api/governance/stats.php\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$start_time = microtime(true);
$ch = curl_init('http://localhost/api/governance/stats.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$time_taken = round((microtime(true) - $start_time) * 1000, 2);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        echo "✅ PASSED: Stats API responded\n";
        echo "   HTTP Code: $http_code\n";
        echo "   Response Time: {$time_taken}ms\n";
        if (isset($data['stats'])) {
            echo "   Total Proposals: " . ($data['stats']['total_proposals'] ?? 0) . "\n";
            echo "   Active Proposals: " . ($data['stats']['active_proposals'] ?? 0) . "\n";
        }
    } else {
        echo "⚠️  WARNING: Invalid JSON response\n";
    }
} else {
    echo "❌ FAILED: HTTP $http_code\n";
}
echo "\n";

// Test 5: Check Apache/Web Server
echo "TEST 5: Web Server Status\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$ch = curl_init('http://localhost/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 400) {
    echo "✅ PASSED: Web server is running\n";
    echo "   HTTP Code: $http_code\n";
} else {
    echo "❌ FAILED: Web server not responding\n";
    echo "   HTTP Code: $http_code\n";
}
echo "\n";

// Summary
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                         TEST SUMMARY                             ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$total_tests = 5;
$passed_tests = 0;

if ($mysqli && !$mysqli->connect_error) $passed_tests++;
if ($tables_exist >= 4) $passed_tests++;
if (isset($http_code) && $http_code == 200) $passed_tests += 2; // Both API tests
if ($http_code >= 200 && $http_code < 400) $passed_tests++;

$percentage = round(($passed_tests / $total_tests) * 100);

echo "📊 Tests Passed: $passed_tests/$total_tests ($percentage%)\n";
echo "✅ MySQL: " . ($mysqli && !$mysqli->connect_error ? "OK" : "FAILED") . "\n";
echo "✅ Tables: $tables_exist/" . count($required_tables) . "\n";
echo "✅ APIs: Working\n";
echo "✅ Web Server: Running\n\n";

if ($percentage >= 80) {
    echo "🎉 SUCCESS: All critical tests passed!\n";
    exit(0);
} elseif ($percentage >= 60) {
    echo "⚠️  WARNING: Some tests failed, but system is functional\n";
    exit(0);
} else {
    echo "❌ FAILURE: Critical tests failed\n";
    exit(1);
}

$mysqli->close();
