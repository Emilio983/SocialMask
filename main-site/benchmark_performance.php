#!/usr/bin/env php
<?php
/**
 * ============================================
 * PERFORMANCE BENCHMARK SCRIPT
 * ============================================
 * Tests API response times and identifies bottlenecks
 */

class PerformanceBenchmark {
    private $baseUrl;
    private $results = [];
    
    public function __construct($baseUrl = 'http://localhost') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * Run all benchmarks
     */
    public function runBenchmarks() {
        $this->printHeader("PERFORMANCE BENCHMARK");
        
        echo "\nRunning performance tests...\n\n";
        
        // Test each endpoint
        $this->benchmarkEndpoint('GET', '/api/governance/proposals.php', 'Get all proposals');
        $this->benchmarkEndpoint('GET', '/api/governance/proposals.php?status=active', 'Filter by status');
        $this->benchmarkEndpoint('GET', '/api/governance/proposals.php?limit=10', 'Paginated results');
        $this->benchmarkEndpoint('GET', '/api/governance/stats.php', 'Get statistics');
        
        // Print results
        $this->printResults();
        $this->printRecommendations();
    }
    
    /**
     * Benchmark a single endpoint
     */
    private function benchmarkEndpoint($method, $endpoint, $description) {
        $url = $this->baseUrl . $endpoint;
        
        echo "Testing: $description\n";
        echo "  URL: $endpoint\n";
        
        // Run multiple times and get average
        $times = [];
        $iterations = 10;
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $end = microtime(true);
            $time = ($end - $start) * 1000; // Convert to milliseconds
            
            $times[] = $time;
        }
        
        // Calculate statistics
        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);
        
        // Determine status
        $status = $avg < 100 ? '✓ EXCELLENT' : 
                 ($avg < 200 ? '✓ GOOD' : 
                 ($avg < 500 ? '⚠ FAIR' : '✗ SLOW'));
        
        $color = $avg < 100 ? "\033[32m" : 
                ($avg < 200 ? "\033[36m" : 
                ($avg < 500 ? "\033[33m" : "\033[31m"));
        
        echo $color;
        echo "  Average: " . round($avg, 2) . "ms\n";
        echo "  Min: " . round($min, 2) . "ms\n";
        echo "  Max: " . round($max, 2) . "ms\n";
        echo "  Status: $status\n";
        echo "\033[0m\n";
        
        $this->results[] = [
            'description' => $description,
            'endpoint' => $endpoint,
            'avg' => $avg,
            'min' => $min,
            'max' => $max,
            'status' => $status
        ];
    }
    
    /**
     * Print results table
     */
    private function printResults() {
        $this->printHeader("BENCHMARK RESULTS");
        
        echo sprintf("%-40s %-10s %-10s %-10s %-15s\n", 
            "Endpoint", "Avg (ms)", "Min (ms)", "Max (ms)", "Status");
        echo str_repeat("-", 85) . "\n";
        
        foreach ($this->results as $result) {
            echo sprintf("%-40s %-10s %-10s %-10s %-15s\n",
                substr($result['description'], 0, 39),
                round($result['avg'], 2),
                round($result['min'], 2),
                round($result['max'], 2),
                $result['status']
            );
        }
        
        echo "\n";
    }
    
    /**
     * Print performance recommendations
     */
    private function printRecommendations() {
        $this->printHeader("RECOMMENDATIONS");
        
        $slowEndpoints = array_filter($this->results, function($r) {
            return $r['avg'] > 200;
        });
        
        if (empty($slowEndpoints)) {
            echo "\033[32m✓ All endpoints performing well!\033[0m\n\n";
            return;
        }
        
        echo "\033[33mSlow endpoints detected. Consider:\033[0m\n\n";
        
        foreach ($slowEndpoints as $endpoint) {
            echo "• {$endpoint['description']} ({$endpoint['avg']}ms)\n";
            
            // Specific recommendations
            if (strpos($endpoint['endpoint'], 'proposals.php') !== false) {
                echo "  - Add database indexes on status, created_at\n";
                echo "  - Implement result caching (5 min)\n";
                echo "  - Optimize SQL joins\n";
            }
            
            if (strpos($endpoint['endpoint'], 'stats.php') !== false) {
                echo "  - Cache statistics (15 min)\n";
                echo "  - Pre-calculate aggregates\n";
                echo "  - Use Redis for caching\n";
            }
            
            echo "\n";
        }
        
        echo "General optimizations:\n";
        echo "  1. Enable OPcache for PHP\n";
        echo "  2. Use Redis/Memcached for session storage\n";
        echo "  3. Enable gzip compression\n";
        echo "  4. Optimize database queries with EXPLAIN\n";
        echo "  5. Add CDN for static assets\n";
        echo "\n";
    }
    
    /**
     * Print header
     */
    private function printHeader($text) {
        echo "\033[1m\033[34m\n";
        echo "╔══════════════════════════════════════════════════════════════════════╗\n";
        echo "║  " . str_pad($text, 66) . "  ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════╝\n";
        echo "\033[0m";
    }
}

// Check if MySQL is running
echo "Checking MySQL connection...\n";
require_once __DIR__ . '/config/connection.php';

if ($conn->connect_error) {
    echo "\033[31m✗ MySQL not connected. Please start XAMPP/MySQL.\033[0m\n";
    exit(1);
}

echo "\033[32m✓ MySQL connected\033[0m\n\n";

// Run benchmarks
$benchmark = new PerformanceBenchmark();
$benchmark->runBenchmarks();
