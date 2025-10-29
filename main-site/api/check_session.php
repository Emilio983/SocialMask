<?php
// ============================================
// CHECK SESSION API
// ============================================

// IMPORTANTE: Headers PRIMERO, antes de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Configuración de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Verificar y cargar archivos necesarios
    $compatibility_file = __DIR__ . '/compatibility_functions.php';
    if (file_exists($compatibility_file)) {
        require_once $compatibility_file;
    } else {
        throw new Exception('Compatibility functions file not found');
    }

    $conn_file = __DIR__ . '/../config/connection.php';
    if (!file_exists($conn_file)) {
        throw new Exception('Connection config file not found');
    }
    require_once $conn_file;

    $utils_file = __DIR__ . '/utils.php';
    if (file_exists($utils_file)) {
        require_once $utils_file;
    }

    // La sesión ya fue iniciada en connection.php
    // Inicializar variables para evitar warnings
    $isLogged = false;
    $user_data = null;

    // Check if session variables are set AND valid
    if (isset($_SESSION) && is_array($_SESSION) && !empty($_SESSION)) {
        // IMPORTANTE: Verificar que user_id existe Y no está vacío
        $has_user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && $_SESSION['user_id'] !== '';

        // También verificar que el usuario existe en la base de datos
        if ($has_user_id && isset($pdo)) {
            $session_user_id = $_SESSION['user_id']; // Guardar antes de limpiar
            try {
                $stmt = $pdo->prepare("SELECT user_id, username, wallet_address FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$session_user_id]);
                $db_user = $stmt->fetch();

                if ($db_user) {
                    $isLogged = true;
                    $user_data = [
                        'user_id' => $db_user['user_id'],
                        'username' => $db_user['username'],
                        'wallet_address' => $db_user['wallet_address']
                    ];
                } else {
                    // Usuario no existe en DB, limpiar sesión fantasma
                    error_log("Session cleanup: User ID $session_user_id not found in database");
                    $_SESSION = array();
                    $isLogged = false;
                }
            } catch (Exception $e) {
                error_log("Session validation error: " . $e->getMessage());
                // Si hay error de DB, no autenticar y limpiar
                $_SESSION = array();
                $isLogged = false;
            }
        } else {
            // No hay user_id válido, limpiar sesión
            if (!empty($_SESSION)) {
                error_log("Session cleanup: Invalid or empty user_id");
                $_SESSION = array();
            }
            $isLogged = false;
        }
    } else {
        // Sesión vacía o no existe
        $isLogged = false;
    }

    // IMPORTANTE: Asegurarse de que SIEMPRE devolvemos un estado claro
    echo json_encode([
        'success' => true,
        'authenticated' => $isLogged,
        'user' => $user_data
    ]);

} catch (Throwable $e) {
    error_log("ERROR - check_session.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Internal server error'
    ]);
}
?>