<?php
// ============================================
// PATH HELPER - Standardized path resolution
// ============================================

/**
 * Get project root directory
 * @return string Absolute path to project root
 */
function getProjectRoot() {
    // Project root is one level up from api directory
    return dirname(__DIR__);
}

/**
 * Get absolute path to config directory
 * @return string
 */
function getConfigPath($file = null) {
    $path = getProjectRoot() . '/config';
    return $file ? $path . '/' . $file : $path;
}

/**
 * Get absolute path to api directory
 * @return string
 */
function getApiPath($file = null) {
    $path = getProjectRoot() . '/api';
    return $file ? $path . '/' . $file : $path;
}

/**
 * Get absolute path to pages directory
 * @return string
 */
function getPagesPath($file = null) {
    $path = getProjectRoot() . '/pages';
    return $file ? $path . '/' . $file : $path;
}

/**
 * Get absolute path to components directory
 * @return string
 */
function getComponentsPath($file = null) {
    $path = getProjectRoot() . '/components';
    return $file ? $path . '/' . $file : $path;
}

/**
 * Get absolute path to database directory
 * @return string
 */
function getDatabasePath($file = null) {
    $path = getProjectRoot() . '/database';
    return $file ? $path . '/' . $file : $path;
}

/**
 * Get absolute path to assets directory
 * @return string
 */
function getAssetsPath($file = null) {
    $path = getProjectRoot() . '/assets';
    return $file ? $path . '/' . $file : $path;
}

/**
 * Get absolute path to uploads directory
 * @return string
 */
function getUploadsPath($file = null) {
    $path = getProjectRoot() . '/uploads';
    return $file ? $path . '/' . $file : $path;
}

/**
 * Safely require a file with existence check
 * @param string $file_path Absolute path to file
 * @param bool $once Use require_once instead of require
 * @return bool True if successfully included
 */
function safeRequire($file_path, $once = true) {
    if (!file_exists($file_path)) {
        error_log("ERROR - File not found: " . $file_path);
        return false;
    }

    if ($once) {
        require_once $file_path;
    } else {
        require $file_path;
    }

    return true;
}

/**
 * Safely include a file with existence check
 * @param string $file_path Absolute path to file
 * @param bool $once Use include_once instead of include
 * @return bool True if successfully included
 */
function safeInclude($file_path, $once = true) {
    if (!file_exists($file_path)) {
        error_log("WARNING - File not found: " . $file_path);
        return false;
    }

    if ($once) {
        include_once $file_path;
    } else {
        include $file_path;
    }

    return true;
}

/**
 * Get relative URL path from file system path
 * @param string $file_path Absolute file system path
 * @return string Relative URL path
 */
function getUrlPath($file_path) {
    $project_root = getProjectRoot();
    $relative = str_replace($project_root, '', $file_path);
    $relative = str_replace('\\', '/', $relative);
    return ltrim($relative, '/');
}

/**
 * Check if running in CLI mode
 * @return bool
 */
function isCli() {
    return php_sapi_name() === 'cli' || defined('STDIN');
}

/**
 * Check if running from API directory
 * @return bool
 */
function isApiRequest() {
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    return strpos($script_name, '/api/') !== false ||
           strpos($request_uri, '/api/') !== false;
}

/**
 * Normalize path separators
 * @param string $path
 * @return string Path with forward slashes
 */
function normalizePath($path) {
    return str_replace('\\', '/', $path);
}

?>