<?php
/**
 * ============================================
 * SESSION HELPER FUNCTIONS
 * ============================================
 * Funciones de ayuda para verificar sesiones sin output
 */

if (!function_exists('isLoggedIn')) {
    /**
     * Verifica si el usuario está autenticado
     * @return bool
     */
    function isLoggedIn() {
        // Verificar que la sesión esté iniciada
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Verificar que exista user_id en la sesión y no esté vacío
        return isset($_SESSION['user_id']) &&
               !empty($_SESSION['user_id']) &&
               $_SESSION['user_id'] !== '' &&
               is_numeric($_SESSION['user_id']);
    }
}

if (!function_exists('getUserId')) {
    /**
     * Obtiene el ID del usuario autenticado
     * @return int|null
     */
    function getUserId() {
        if (isLoggedIn()) {
            return (int)$_SESSION['user_id'];
        }
        return null;
    }
}

if (!function_exists('requireAuth')) {
    /**
     * Requiere autenticación, devuelve error 401 si no está autenticado
     * @return void
     */
    function requireAuth() {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Login required'
            ]);
            exit;
        }
    }
}
