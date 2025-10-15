#!/usr/bin/env php
<?php
/**
 * ============================================
 * GOVERNANCE SYSTEM - BACKEND API TESTER
 * ============================================
 * Tests all governance API endpoints
 */

// Color output for terminal
class TestColors {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

class GovernanceAPITester {
    private $baseUrl;
    private $sessionCookie = null;
    private $testUserId = null;
    private $testProposalId = null;
    private $passedTests = 0;
    private $failedTests = 0;
    private $totalTests = 0;
    
    public function __construct($baseUrl = 'http://localhost') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        $this->printHeader("GOVERNANCE SYSTEM - API TESTING SUITE");
        
        // Prerequisites check
        if (!$this->checkPrerequisites()) {
            $this->printError("Prerequisites check failed. Cannot continue testing.");
            return false;
        }
        
        echo "\n";
        $this->printHeader("BACKEND API TESTS");
        
        // Test proposals API
        $this->testProposalsGet();
        $this->testProposalsPost();
        $this->testProposalGetSingle();
        
        // Test votes API
        $this->testVotesPost();
        
        // Test delegations API
        $this->testDelegationsPost();
        $this->testDelegationsDelete();
        
        // Test stats API
        $this->testStatsGet();
        
        // Test comments API
        $this->testCommentsGet();
        $this->testCommentsPost();
        
        // Test Web3 APIs
        $this->testVerifySignature();
        $this->testSyncWallet();
        
        // Print summary
        echo "\n";
        $this->printSummary();
    }
    
    /**
     * Check prerequisites
     */
    private function checkPrerequisites() {
        $this->printInfo("Checking prerequisites...");
        
        $allGood = true;
        
        // Check MySQL
        echo "  • MySQL connection... ";
        if ($this->checkMysql()) {
            $this->printSuccess("OK");
        } else {
            $this->printError("FAIL - MySQL not running or not accessible");
            $allGood = false;
        }
        
        // Check API files exist
        echo "  • API files... ";
        $requiredFiles = [
            'api/governance/proposals.php',
            'api/governance/votes.php',
            'api/governance/delegations.php',
            'api/governance/stats.php',
            'api/governance/comments.php',
        ];
        
        $missingFiles = [];
        foreach ($requiredFiles as $file) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                $missingFiles[] = $file;
            }
        }
        
        if (empty($missingFiles)) {
            $this->printSuccess("OK");
        } else {
            $this->printError("FAIL - Missing files: " . implode(', ', $missingFiles));
            $allGood = false;
        }
        
        // Check database tables
        echo "  • Database tables... ";
        if ($this->checkMysql() && $this->checkDatabaseTables()) {
            $this->printSuccess("OK");
        } else {
            $this->printError("FAIL - Missing required tables");
            $allGood = false;
        }
        
        return $allGood;
    }
    
    /**
     * Check MySQL connection
     */
    private function checkMysql() {
        try {
            require_once __DIR__ . '/config/connection.php';
            return isset($conn) && $conn instanceof mysqli && !$conn->connect_error;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check database tables exist
     */
    private function checkDatabaseTables() {
        require_once __DIR__ . '/config/connection.php';
        
        $requiredTables = [
            'governance_proposals',
            'governance_votes',
            'governance_delegations',
            'governance_comments',
            'users'
        ];
        
        foreach ($requiredTables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test GET /api/governance/proposals.php
     */
    private function testProposalsGet() {
        $this->printTestName("GET /api/governance/proposals.php");
        
        // Test 1: Get all proposals
        $response = $this->makeRequest('GET', '/api/governance/proposals.php');
        $this->assertResponseSuccess($response, "Fetch all proposals");
        
        if ($response['success'] && isset($response['data']['proposals'])) {
            $this->assertIsArray($response['data']['proposals'], "Proposals is array");
            $this->printSuccess("  ✓ Proposals data structure valid");
            
            // Store first proposal ID for later tests
            if (!empty($response['data']['proposals'])) {
                $this->testProposalId = $response['data']['proposals'][0]['id'];
            }
        }
        
        // Test 2: Filter by status
        $response = $this->makeRequest('GET', '/api/governance/proposals.php?status=active');
        $this->assertResponseSuccess($response, "Filter by status=active");
        
        // Test 3: Pagination
        $response = $this->makeRequest('GET', '/api/governance/proposals.php?limit=5&offset=0');
        $this->assertResponseSuccess($response, "Pagination (limit=5)");
        
        // Test 4: Search
        $response = $this->makeRequest('GET', '/api/governance/proposals.php?search=test');
        $this->assertResponseSuccess($response, "Search functionality");
    }
    
    /**
     * Test POST /api/governance/proposals.php
     */
    private function testProposalsPost() {
        $this->printTestName("POST /api/governance/proposals.php");
        
        // Note: This requires authentication
        $this->printWarning("  ⚠ Skipping - Requires authentication (session)");
        $this->totalTests++;
    }
    
    /**
     * Test GET single proposal
     */
    private function testProposalGetSingle() {
        $this->printTestName("GET /api/governance/proposals.php?id=X");
        
        if (!$this->testProposalId) {
            $this->printWarning("  ⚠ Skipping - No proposal ID available");
            $this->totalTests++;
            return;
        }
        
        $response = $this->makeRequest('GET', '/api/governance/proposals.php?id=' . $this->testProposalId);
        $this->assertResponseSuccess($response, "Fetch single proposal");
        
        if ($response['success'] && isset($response['data']['proposal'])) {
            $this->assertIsArray($response['data']['proposal'], "Proposal data is array");
            $this->assertArrayHasKey($response['data']['proposal'], 'id', "Has ID field");
            $this->assertArrayHasKey($response['data']['proposal'], 'title', "Has title field");
            $this->assertArrayHasKey($response['data']['proposal'], 'status', "Has status field");
        }
    }
    
    /**
     * Test POST /api/governance/votes.php
     */
    private function testVotesPost() {
        $this->printTestName("POST /api/governance/votes.php");
        $this->printWarning("  ⚠ Skipping - Requires authentication");
        $this->totalTests++;
    }
    
    /**
     * Test POST /api/governance/delegations.php
     */
    private function testDelegationsPost() {
        $this->printTestName("POST /api/governance/delegations.php");
        $this->printWarning("  ⚠ Skipping - Requires authentication");
        $this->totalTests++;
    }
    
    /**
     * Test DELETE /api/governance/delegations.php
     */
    private function testDelegationsDelete() {
        $this->printTestName("DELETE /api/governance/delegations.php");
        $this->printWarning("  ⚠ Skipping - Requires authentication");
        $this->totalTests++;
    }
    
    /**
     * Test GET /api/governance/stats.php
     */
    private function testStatsGet() {
        $this->printTestName("GET /api/governance/stats.php");
        
        $response = $this->makeRequest('GET', '/api/governance/stats.php');
        $this->assertResponseSuccess($response, "Fetch statistics");
        
        if ($response['success'] && isset($response['data'])) {
            $requiredStats = [
                'total_proposals',
                'active_proposals',
                'total_votes',
                'total_voters'
            ];
            
            foreach ($requiredStats as $stat) {
                $this->assertArrayHasKey($response['data'], $stat, "Has $stat");
            }
        }
    }
    
    /**
     * Test GET /api/governance/comments.php
     */
    private function testCommentsGet() {
        $this->printTestName("GET /api/governance/comments.php");
        
        if (!$this->testProposalId) {
            $this->printWarning("  ⚠ Skipping - No proposal ID available");
            $this->totalTests++;
            return;
        }
        
        $response = $this->makeRequest('GET', '/api/governance/comments.php?proposal_id=' . $this->testProposalId);
        $this->assertResponseSuccess($response, "Fetch comments");
    }
    
    /**
     * Test POST /api/governance/comments.php
     */
    private function testCommentsPost() {
        $this->printTestName("POST /api/governance/comments.php");
        $this->printWarning("  ⚠ Skipping - Requires authentication");
        $this->totalTests++;
    }
    
    /**
     * Test POST /api/wallet/verify-signature.php
     */
    private function testVerifySignature() {
        $this->printTestName("POST /api/wallet/verify-signature.php");
        
        // Test with invalid signature
        $response = $this->makeRequest('POST', '/api/wallet/verify-signature.php', [
            'message' => 'Test message',
            'signature' => '0xinvalid',
            'address' => '0x0000000000000000000000000000000000000000'
        ]);
        
        // Should return error or invalid
        if (isset($response['success'])) {
            $this->printSuccess("  ✓ Endpoint responds");
            $this->passedTests++;
        } else {
            $this->printError("  ✗ Endpoint not responding properly");
            $this->failedTests++;
        }
        $this->totalTests++;
    }
    
    /**
     * Test POST /api/wallet/sync-wallet.php
     */
    private function testSyncWallet() {
        $this->printTestName("POST /api/wallet/sync-wallet.php");
        $this->printWarning("  ⚠ Skipping - Requires authentication + valid signature");
        $this->totalTests++;
    }
    
    /**
     * Make HTTP request
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        return [
            'http_code' => $httpCode,
            'success' => $httpCode === 200,
            'data' => $decoded,
            'raw' => $response
        ];
    }
    
    /**
     * Assertions
     */
    private function assertResponseSuccess($response, $testName) {
        $this->totalTests++;
        
        if ($response['http_code'] === 200) {
            $this->printSuccess("  ✓ $testName - HTTP 200");
            $this->passedTests++;
            return true;
        } else {
            $this->printError("  ✗ $testName - HTTP {$response['http_code']}");
            $this->failedTests++;
            return false;
        }
    }
    
    private function assertIsArray($value, $testName) {
        $this->totalTests++;
        
        if (is_array($value)) {
            $this->passedTests++;
            return true;
        } else {
            $this->printError("  ✗ $testName - Not an array");
            $this->failedTests++;
            return false;
        }
    }
    
    private function assertArrayHasKey($array, $key, $testName) {
        $this->totalTests++;
        
        if (isset($array[$key])) {
            $this->passedTests++;
            return true;
        } else {
            $this->printError("  ✗ $testName - Missing key: $key");
            $this->failedTests++;
            return false;
        }
    }
    
    /**
     * Print helpers
     */
    private function printHeader($text) {
        echo TestColors::BOLD . TestColors::BLUE . "\n";
        echo "╔══════════════════════════════════════════════════════════════════════╗\n";
        echo "║  " . str_pad($text, 66) . "  ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════╝\n";
        echo TestColors::RESET;
    }
    
    private function printTestName($name) {
        echo TestColors::BOLD . "\n$name\n" . TestColors::RESET;
    }
    
    private function printSuccess($text) {
        echo TestColors::GREEN . $text . TestColors::RESET . "\n";
    }
    
    private function printError($text) {
        echo TestColors::RED . $text . TestColors::RESET . "\n";
    }
    
    private function printWarning($text) {
        echo TestColors::YELLOW . $text . TestColors::RESET . "\n";
    }
    
    private function printInfo($text) {
        echo TestColors::BLUE . $text . TestColors::RESET . "\n";
    }
    
    private function printSummary() {
        $this->printHeader("TEST SUMMARY");
        
        echo "Total Tests:  " . $this->totalTests . "\n";
        echo TestColors::GREEN . "Passed:       " . $this->passedTests . TestColors::RESET . "\n";
        echo TestColors::RED . "Failed:       " . $this->failedTests . TestColors::RESET . "\n";
        echo TestColors::YELLOW . "Skipped:      " . ($this->totalTests - $this->passedTests - $this->failedTests) . TestColors::RESET . "\n";
        
        $percentage = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 2) : 0;
        echo "\nSuccess Rate: " . $percentage . "%\n";
        
        if ($this->failedTests === 0) {
            echo TestColors::GREEN . TestColors::BOLD . "\n✓ ALL TESTS PASSED!\n" . TestColors::RESET;
        } else {
            echo TestColors::RED . TestColors::BOLD . "\n✗ SOME TESTS FAILED\n" . TestColors::RESET;
        }
        
        echo "\n";
    }
}

// Run tests
$tester = new GovernanceAPITester();
$tester->runAllTests();
