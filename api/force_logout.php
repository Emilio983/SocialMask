<?php
// ============================================
// FORCE LOGOUT - Limpiar sesión forzadamente
// Abre este archivo en el navegador si tienes problemas de sesión
// ============================================

// Forzar limpieza completa de sesión

// PASO 1: Limpiar TODAS las posibles cookies de sesión (incluidas las antiguas)
$possible_session_names = ['PHPSESSID', 'thesocialmask_session', 'thesocialmaskSESSID'];

foreach ($possible_session_names as $name) {
    if (isset($_COOKIE[$name])) {
        setcookie($name, '', time() - 42000, '/');
        setcookie($name, '', time() - 42000, '/', $_SERVER['HTTP_HOST'] ?? '');
        unset($_COOKIE[$name]);
    }
}

// PASO 2: Si hay sesión activa, destruirla
if (session_status() === PHP_SESSION_ACTIVE) {
    // Guardar el nombre de la sesión antes de destruir
    $session_name = session_name();

    // Limpiar TODAS las variables de sesión
    $_SESSION = array();

    // Eliminar la cookie de sesión
    if (isset($_COOKIE[$session_name])) {
        $params = session_get_cookie_params();
        setcookie(
            $session_name,
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
        setcookie($session_name, '', time() - 42000, '/');
    }

    // Destruir la sesión
    session_destroy();
}

// PASO 3: Iniciar nueva sesión limpia con el nombre CORRECTO
session_name('thesocialmask_session');
session_start();

// Asegurar que está vacía
$_SESSION = array();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión Limpiada - The Social Mask</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0D1117;
            color: #C9D1D9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: #161B22;
            border: 1px solid #30363D;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            text-align: center;
        }
        .success-icon {
            width: 64px;
            height: 64px;
            background: rgba(40, 167, 69, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .success-icon svg {
            width: 32px;
            height: 32px;
            color: #28a745;
        }
        h1 {
            color: #28a745;
            margin: 0 0 10px;
            font-size: 24px;
        }
        p {
            color: #8B949E;
            margin: 0 0 30px;
            line-height: 1.6;
        }
        .button {
            display: inline-block;
            background: #3B82F6;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .button:hover {
            background: #2563EB;
            transform: translateY(-1px);
        }
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            text-align: left;
        }
        .info-box h3 {
            color: #3B82F6;
            font-size: 14px;
            margin: 0 0 10px;
        }
        .info-box ul {
            margin: 0;
            padding-left: 20px;
            color: #8B949E;
            font-size: 13px;
        }
        .info-box li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1>✅ Sesión Limpiada Exitosamente</h1>

        <p>Tu sesión ha sido completamente eliminada del sistema. Ahora puedes iniciar sesión nuevamente sin problemas.</p>

        <a href="/login" class="button">Ir a Iniciar Sesión</a>

        <div class="info-box">
            <h3>📋 ¿Qué se limpió?</h3>
            <ul>
                <li>Todas las variables de sesión</li>
                <li>Cookie de sesión del navegador</li>
                <li>Datos temporales almacenados</li>
                <li>Sesión fantasma (si existía)</li>
            </ul>
        </div>

        <div class="info-box" style="margin-top: 15px; background: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.3);">
            <h3 style="color: #dc3545;">⚠️ Si el problema persiste</h3>
            <ul>
                <li>Borra las cookies de tu navegador manualmente</li>
                <li>Prueba en modo incógnito/privado</li>
                <li>Limpia el caché del navegador</li>
            </ul>
        </div>
    </div>

    <script>
        console.log('✅ Sesión limpiada completamente');
        console.log('Session ID anterior eliminado');
        console.log('Listo para nuevo login');

        // Limpiar TODAS las cookies desde JavaScript también
        console.log('🧹 Limpiando cookies del navegador...');
        const cookies = ['PHPSESSID', 'thesocialmask_session', 'thesocialmaskSESSID'];
        cookies.forEach(name => {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            console.log('   - Eliminada cookie:', name);
        });

        // Limpiar storage también
        sessionStorage.clear();
        localStorage.removeItem('user');
        console.log('✅ Limpieza completa terminada');
    </script>
</body>
</html>