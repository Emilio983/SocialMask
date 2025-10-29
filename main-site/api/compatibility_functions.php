<?php
if (!function_exists('get_flash')) {
    function get_flash($name = null) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // Si no se proporciona nombre, devolver array vacío
        if ($name === null) {
            return [];
        }
        // Si el nombre es string vacío, devolver todo el array de flash
        if ($name === '') {
            return isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
        }
        // Devolver mensaje específico
        if (isset($_SESSION['flash'][$name])) {
            $message = $_SESSION['flash'][$name];
            unset($_SESSION['flash'][$name]);
            return $message;
        }
        return null;
    }
}

if (!function_exists('set_flash')) {
    function set_flash($name, $message) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['flash'][$name] = $message;
    }
}

if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('is_ajax')) {
    function is_ajax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

// Compatibility functions loaded
?>