<?php
// ============================================
// RATE LIMITER - Protection against brute force
// ============================================

/**
 * Simple rate limiter using file-based storage
 * For production, consider using Redis or Memcached
 */
class RateLimiter {
    private $storage_path;
    private $max_requests;
    private $time_window;

    /**
    * @param int $max_requests Maximum requests allowed per time window
     * @param int $time_window Time window in seconds
     */
    public function __construct($max_requests = 10, $time_window = 60) {
        $this->storage_path = __DIR__ . '/../.rate_limit/';
        $this->max_requests = $max_requests;
        $this->time_window = $time_window;

        // Create storage directory if it doesn't exist
        if (!file_exists($this->storage_path)) {
            @mkdir($this->storage_path, 0755, true);
        }
    }

    /**
     * Check if request is allowed
     * @param string $identifier Identifier (IP, wallet address, etc.)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public function check($identifier) {
        $identifier = $this->sanitizeIdentifier($identifier);
        $file_path = $this->storage_path . md5($identifier) . '.json';

        // Read current state
        $state = $this->readState($file_path);

        // Clean old requests
        $state = $this->cleanOldRequests($state);

        // Check if limit exceeded
        $request_count = count($state['requests']);
        $allowed = $request_count < $this->max_requests;

        if ($allowed) {
            // Add new request
            $state['requests'][] = time();
            $this->writeState($file_path, $state);
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $this->max_requests - $request_count - ($allowed ? 1 : 0)),
            'reset_at' => time() + $this->time_window,
            'retry_after' => $allowed ? 0 : $this->getRetryAfter($state)
        ];
    }

    /**
     * Read state from file
     */
    private function readState($file_path) {
        if (!file_exists($file_path)) {
            return ['requests' => []];
        }

        $content = @file_get_contents($file_path);
        if ($content === false) {
            return ['requests' => []];
        }

        $state = json_decode($content, true);
        if (!is_array($state) || !isset($state['requests'])) {
            return ['requests' => []];
        }

        return $state;
    }

    /**
     * Write state to file
     */
    private function writeState($file_path, $state) {
        @file_put_contents($file_path, json_encode($state), LOCK_EX);
    }

    /**
     * Clean requests older than time window
     */
    private function cleanOldRequests($state) {
        $cutoff = time() - $this->time_window;
        $state['requests'] = array_filter($state['requests'], function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        return $state;
    }

    /**
     * Calculate retry after time
     */
    private function getRetryAfter($state) {
        if (empty($state['requests'])) {
            return 0;
        }

        $oldest = min($state['requests']);
        return max(0, ($oldest + $this->time_window) - time());
    }

    /**
     * Sanitize identifier
     */
    private function sanitizeIdentifier($identifier) {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $identifier);
    }

    /**
     * Reset limit for identifier
     */
    public function reset($identifier) {
        $identifier = $this->sanitizeIdentifier($identifier);
        $file_path = $this->storage_path . md5($identifier) . '.json';
        @unlink($file_path);
    }
}

/**
 * Helper function to check rate limit
 * @param string $identifier Identifier (IP, wallet, etc.)
 * @param int $max_requests Max requests per window
 * @param int $time_window Time window in seconds
 * @return bool True if allowed, false if rate limited
 */
function checkRateLimit($identifier, $max_requests = 10, $time_window = 60) {
    $limiter = new RateLimiter($max_requests, $time_window);
    $result = $limiter->check($identifier);

    if (!$result['allowed']) {
        http_response_code(429); // Too Many Requests
        header('Retry-After: ' . $result['retry_after']);
        header('X-RateLimit-Limit: ' . $max_requests);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . $result['reset_at']);

        echo json_encode([
            'success' => false,
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $result['retry_after']
        ]);
        exit;
    }

    // Set rate limit headers
    header('X-RateLimit-Limit: ' . $max_requests);
    header('X-RateLimit-Remaining: ' . $result['remaining']);
    header('X-RateLimit-Reset: ' . $result['reset_at']);

    return true;
}

?>