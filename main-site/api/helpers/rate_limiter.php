<?php
/**
 * RATE LIMITER
 * Simple file-based rate limiting
 *
 * Usage:
 *   require_once __DIR__ . '/helpers/rate_limiter.php';
 *   if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 5, 60)) {
 *       http_response_code(429);
 *       echo json_encode(['error' => 'Too many requests']);
 *       exit;
 *   }
 */

/**
 * Check if request should be rate limited
 *
 * @param string $identifier Unique identifier (IP, user_id, etc)
 * @param int $max_requests Maximum requests allowed in time window
 * @param int $time_window Time window in seconds
 * @return bool True if allowed, false if rate limit exceeded
 */
function checkRateLimit($identifier, $max_requests = 5, $time_window = 60) {
    $cache_dir = sys_get_temp_dir() . '/thesocialmask_rate_limit';

    // Create directory if not exists
    if (!file_exists($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $cache_file = $cache_dir . '/rl_' . md5($identifier);

    // Clean old cache files (older than 1 hour)
    cleanOldCacheFiles($cache_dir);

    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        $time_passed = time() - $data['timestamp'];

        if ($time_passed < $time_window) {
            // Still within time window
            if ($data['count'] >= $max_requests) {
                // Rate limit exceeded
                return false;
            }
            // Increment count
            $data['count']++;
        } else {
            // Time window expired, reset counter
            $data = ['count' => 1, 'timestamp' => time()];
        }
    } else {
        // First request
        $data = ['count' => 1, 'timestamp' => time()];
    }

    // Save updated data
    file_put_contents($cache_file, json_encode($data));
    return true;
}

/**
 * Clean old cache files to prevent disk buildup
 */
function cleanOldCacheFiles($cache_dir) {
    // Only clean 1% of the time to avoid performance impact
    if (rand(1, 100) !== 1) {
        return;
    }

    $files = glob($cache_dir . '/rl_*');
    $one_hour_ago = time() - 3600;

    foreach ($files as $file) {
        if (filemtime($file) < $one_hour_ago) {
            @unlink($file);
        }
    }
}

/**
 * Get rate limit info (for debugging)
 */
function getRateLimitInfo($identifier) {
    $cache_file = sys_get_temp_dir() . '/thesocialmask_rate_limit/rl_' . md5($identifier);

    if (!file_exists($cache_file)) {
        return ['count' => 0, 'timestamp' => time()];
    }

    return json_decode(file_get_contents($cache_file), true);
}

/**
 * Reset rate limit for identifier (for admin use)
 */
function resetRateLimit($identifier) {
    $cache_file = sys_get_temp_dir() . '/thesocialmask_rate_limit/rl_' . md5($identifier);
    if (file_exists($cache_file)) {
        unlink($cache_file);
    }
}
?>
