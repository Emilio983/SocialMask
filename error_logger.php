<?php
// Enhanced error logging for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_errors.log');

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_types = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'Unknown Error';

    $message = "[$error_type] $errstr in $errfile on line $errline";
    error_log($message);

    // Don't execute PHP internal error handler
    return true;
}

// Custom exception handler
function customExceptionHandler($exception) {
    $message = "Uncaught exception: " . $exception->getMessage() .
               " in " . $exception->getFile() .
               " on line " . $exception->getLine();
    error_log($message);
}

// Set custom handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Log the start of script execution
error_log("=== ERROR LOGGER INITIALIZED ===");
error_log("PHP Version: " . phpversion());
error_log("Current working directory: " . getcwd());
error_log("Memory limit: " . ini_get('memory_limit'));
error_log("Max execution time: " . ini_get('max_execution_time'));

// Test database connection
try {
    error_log("Testing database connection...");

    // ✅ SEGURO: Usar variables de entorno
    require_once __DIR__ . '/config/env.php';
    use TheSocialMask\Config\Env;
    Env::load();

    $dbHost = Env::get('DB_HOST', 'localhost');
    $dbName = Env::get('DB_NAME', 'thesocialmask');
    $dbUser = Env::get('DB_USER', 'root');
    $dbPass = Env::get('DB_PASS', '');

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

error_log("=== ERROR LOGGER READY ===");
?>