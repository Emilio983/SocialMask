<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico de Passkeys - The Social Mask</title>
    <style>
        body { font-family: monospace; background: #0D1117; color: #C9D1D9; padding: 20px; }
        .success { color: #28A745; }
        .error { color: #DC3545; }
        .warning { color: #FFC107; }
        .section { background: #161B22; border: 1px solid #30363D; padding: 15px; margin: 15px 0; border-radius: 8px; }
        h1 { color: #3B82F6; }
        h2 { color: #C9D1D9; border-bottom: 1px solid #30363D; padding-bottom: 10px; }
        pre { background: #0D1117; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .test-btn { background: #3B82F6; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; margin: 5px; }
        .test-btn:hover { background: #2563EB; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico de Sistema de Passkeys</h1>
    <p>Ejecutado: <?php echo date('Y-m-d H:i:s'); ?></p>

    <?php
    require_once __DIR__ . '/config/connection.php';
    require_once __DIR__ . '/utils/node_client.php';

    function testResult($name, $success, $message, $details = null) {
        $class = $success ? 'success' : 'error';
        $icon = $success ? '✅' : '❌';
        echo "<div class='section'>";
        echo "<h3 class='$class'>$icon $name</h3>";
        echo "<p>$message</p>";
        if ($details) {
            echo "<pre>" . htmlspecialchars(print_r($details, true)) . "</pre>";
        }
        echo "</div>";
    }

    // Test 1: Verificar conexión a base de datos
    try {
        $pdo->query('SELECT 1');
        testResult('Base de Datos', true, 'Conexión a MySQL exitosa');
    } catch (Exception $e) {
        testResult('Base de Datos', false, 'Error de conexión: ' . $e->getMessage());
    }

    // Test 2: Verificar backend Node.js
    try {
        $ch = curl_init('http://127.0.0.1:3088/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            testResult('Backend Node.js', true, 'Backend respondiendo correctamente', $data);
        } else {
            testResult('Backend Node.js', false, "HTTP $httpCode - Backend no responde correctamente", $response);
        }
    } catch (Exception $e) {
        testResult('Backend Node.js', false, 'Error al conectar: ' . $e->getMessage());
    }

    // Test 3: Probar endpoint de passkey start
    try {
        $challengeId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );

        $response = nodeApiRequest('POST', 'auth/passkey/start', [
            'challengeId' => $challengeId,
        ]);

        if (isset($response['success']) && $response['success']) {
            testResult('Endpoint passkey/start', true, 'Endpoint funcionando correctamente', $response);
        } else {
            testResult('Endpoint passkey/start', false, 'Respuesta inesperada', $response);
        }
    } catch (Exception $e) {
        testResult('Endpoint passkey/start', false, 'Error: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')');
    }

    // Test 4: Verificar variables de entorno críticas
    $envVars = [
        'NODE_BACKEND_BASE_URL' => defined('NODE_BACKEND_BASE_URL') ? NODE_BACKEND_BASE_URL : 'NO DEFINIDO',
        'WEB3AUTH_CLIENT_ID' => defined('WEB3AUTH_CLIENT_ID') ? substr(WEB3AUTH_CLIENT_ID, 0, 20) . '...' : 'NO DEFINIDO',
        'CHAIN_ID' => defined('CHAIN_ID') ? CHAIN_ID : 'NO DEFINIDO',
    ];
    testResult('Variables de Entorno', true, 'Configuración actual', $envVars);

    // Test 5: Verificar tabla users
    try {
        $result = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'email'")->fetch();
        $emailNull = $result && $result['Null'] === 'YES';
        testResult(
            'Campo email en users',
            $emailNull,
            $emailNull ? 'Campo email acepta NULL (correcto para passkeys)' : 'Campo email NO acepta NULL (puede causar errores)',
            $result
        );
    } catch (Exception $e) {
        testResult('Campo email en users', false, 'Error: ' . $e->getMessage());
    }

    // Test 6: Verificar tabla user_devices
    try {
        $pdo->query("SELECT 1 FROM user_devices LIMIT 1");
        testResult('Tabla user_devices', true, 'Tabla existe y es accesible');
    } catch (Exception $e) {
        testResult('Tabla user_devices', false, 'Tabla no existe o no es accesible: ' . $e->getMessage());
    }
    ?>

    <div class="section">
        <h2>🧪 Pruebas Manuales</h2>
        <p>Haz clic en los botones para probar el flujo completo:</p>
        <button class="test-btn" onclick="window.open('/pages/login.php', '_blank')">
            🔑 Probar Login
        </button>
        <button class="test-btn" onclick="window.open('/pages/register.php', '_blank')">
            ✍️ Probar Registro
        </button>
        <button class="test-btn" onclick="location.reload()">
            🔄 Refrescar Diagnóstico
        </button>
    </div>

    <div class="section">
        <h2>📋 Instrucciones si hay Errores</h2>
        <ol>
            <li>Si el backend Node.js está caído, reinícialo con: <code>pm2 restart thesocialmask-node</code></li>
            <li>Si hay errores de base de datos, ejecuta las migraciones faltantes</li>
            <li>Si los endpoints fallan, revisa los logs: <code>tail -f /var/log/php-fpm/*.log</code></li>
            <li>Limpia el cache del navegador antes de probar: <code>Ctrl+Shift+Delete</code></li>
        </ol>
    </div>

    <p style="text-align: center; color: #8B949E; margin-top: 40px;">
        Diagnóstico completado - <?php echo date('Y-m-d H:i:s'); ?>
    </p>
</body>
</html>
