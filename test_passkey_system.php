<?php
/**
 * Script de Verificación del Sistema de Passkeys
 * Ejecutar en navegador: /test_passkey_system.php
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config/connection.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación del Sistema de Passkeys - The Social Mask</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0D1117;
            color: #C9D1D9;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: #3B82F6;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #8B949E;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            background: #161B22;
            border: 1px solid #30363D;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .section h2 {
            color: #C9D1D9;
            font-size: 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .check-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px;
            background: #0D1117;
            border-radius: 8px;
            border-left: 3px solid transparent;
        }
        .check-item.success { border-left-color: #28A745; }
        .check-item.error { border-left-color: #DC3545; }
        .check-item.warning { border-left-color: #FFC107; }
        .status {
            font-size: 20px;
            flex-shrink: 0;
        }
        .check-content { flex: 1; }
        .check-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 14px;
        }
        .check-desc {
            color: #8B949E;
            font-size: 13px;
            line-height: 1.5;
        }
        .code {
            background: #0D1117;
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 8px;
            border: 1px solid #30363D;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3B82F6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 16px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #2563EB;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge.success { background: rgba(40, 167, 69, 0.2); color: #28A745; }
        .badge.error { background: rgba(220, 53, 69, 0.2); color: #DC3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Verificación del Sistema de Passkeys</h1>
        <p class="subtitle">Diagnóstico completo del sistema de autenticación con passkeys</p>

        <?php
        // 1. Verificar Conexión a Base de Datos
        $checks = [];
        
        try {
            $pdo->query('SELECT 1');
            $checks[] = [
                'status' => 'success',
                'title' => 'Conexión a Base de Datos',
                'desc' => 'Conectado exitosamente a la base de datos MySQL.'
            ];
        } catch (Exception $e) {
            $checks[] = [
                'status' => 'error',
                'title' => 'Conexión a Base de Datos',
                'desc' => 'Error: ' . $e->getMessage()
            ];
        }

        // 2. Verificar Tabla users
        try {
            $result = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'email'")->fetch();
            if ($result && $result['Null'] === 'YES') {
                $checks[] = [
                    'status' => 'success',
                    'title' => 'Campo email (NULL permitido)',
                    'desc' => 'El campo email acepta valores NULL. ✅ Correcto para passkeys.'
                ];
            } else {
                $checks[] = [
                    'status' => 'error',
                    'title' => 'Campo email (NOT NULL)',
                    'desc' => 'El campo email NO acepta NULL. ❌ Esto causará errores en registro con passkeys.'
                ];
            }
        } catch (Exception $e) {
            $checks[] = [
                'status' => 'error',
                'title' => 'Campo email',
                'desc' => 'Error al verificar: ' . $e->getMessage()
            ];
        }

        // 3. Verificar wallet_type ENUM
        try {
            $result = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'wallet_type'")->fetch();
            $enumValues = $result['Type'] ?? '';
            if (strpos($enumValues, 'passkey') !== false) {
                $checks[] = [
                    'status' => 'success',
                    'title' => 'Tipo wallet_type con passkey',
                    'desc' => 'El ENUM de wallet_type incluye \'passkey\'. ✅ Correcto.'
                ];
            } else {
                $checks[] = [
                    'status' => 'error',
                    'title' => 'Tipo wallet_type sin passkey',
                    'desc' => 'El ENUM de wallet_type NO incluye \'passkey\'. ❌ Valores actuales: ' . $enumValues
                ];
            }
        } catch (Exception $e) {
            $checks[] = [
                'status' => 'error',
                'title' => 'Tipo wallet_type',
                'desc' => 'Error al verificar: ' . $e->getMessage()
            ];
        }

        // 4. Verificar Tabla user_devices
        try {
            $pdo->query("SELECT 1 FROM user_devices LIMIT 1");
            $count = $pdo->query("SELECT COUNT(*) as c FROM user_devices")->fetch()['c'];
            $checks[] = [
                'status' => 'success',
                'title' => 'Tabla user_devices',
                'desc' => "Tabla existe. Dispositivos registrados: $count"
            ];
        } catch (Exception $e) {
            $checks[] = [
                'status' => 'warning',
                'title' => 'Tabla user_devices',
                'desc' => 'Tabla no existe o error: ' . $e->getMessage()
            ];
        }

        // 5. Verificar Tabla smart_accounts
        try {
            $pdo->query("SELECT 1 FROM smart_accounts LIMIT 1");
            $count = $pdo->query("SELECT COUNT(*) as c FROM smart_accounts")->fetch()['c'];
            $checks[] = [
                'status' => 'success',
                'title' => 'Tabla smart_accounts',
                'desc' => "Tabla existe. Smart accounts creadas: $count"
            ];
        } catch (Exception $e) {
            $checks[] = [
                'status' => 'warning',
                'title' => 'Tabla smart_accounts',
                'desc' => 'Tabla no existe o error: ' . $e->getMessage()
            ];
        }

        // 6. Verificar Archivos JavaScript
        $jsFiles = [
            'assets/js/auth-passkeys.js' => 'JavaScript de Login',
            'assets/js/auth-register-passkeys.js' => 'JavaScript de Registro',
            'assets/js/toast-alerts.js' => 'Sistema de Alertas'
        ];
        
        foreach ($jsFiles as $file => $name) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $checks[] = [
                    'status' => 'success',
                    'title' => $name,
                    'desc' => "Archivo existe: /$file"
                ];
            } else {
                $checks[] = [
                    'status' => 'error',
                    'title' => $name,
                    'desc' => "Archivo NO existe: /$file"
                ];
            }
        }

        // 7. Verificar Archivos PHP de API
        $apiFiles = [
            'api/auth/passkey_start.php' => 'API Login Start',
            'api/auth/passkey_finish.php' => 'API Login Finish',
            'api/auth/passkey_register_start.php' => 'API Register Start',
            'api/auth/passkey_register_finish.php' => 'API Register Finish'
        ];
        
        foreach ($apiFiles as $file => $name) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $checks[] = [
                    'status' => 'success',
                    'title' => $name,
                    'desc' => "Archivo existe: /$file"
                ];
            } else {
                $checks[] = [
                    'status' => 'error',
                    'title' => $name,
                    'desc' => "Archivo NO existe: /$file"
                ];
            }
        }

        // 8. Contar totales
        $total = count($checks);
        $success = count(array_filter($checks, fn($c) => $c['status'] === 'success'));
        $errors = count(array_filter($checks, fn($c) => $c['status'] === 'error'));
        $warnings = count(array_filter($checks, fn($c) => $c['status'] === 'warning'));
        $percentage = round(($success / $total) * 100);

        echo "<div class='section'>";
        echo "<h2>📊 Resumen</h2>";
        echo "<p><strong>Total de verificaciones:</strong> $total</p>";
        echo "<p><strong>Exitosas:</strong> $success <span class='badge success'>$percentage%</span></p>";
        echo "<p><strong>Errores:</strong> $errors" . ($errors > 0 ? " <span class='badge error'>Requiere atención</span>" : "") . "</p>";
        echo "<p><strong>Advertencias:</strong> $warnings</p>";
        echo "</div>";

        // Mostrar checks
        echo "<div class='section'>";
        echo "<h2>🔍 Verificaciones Detalladas</h2>";
        foreach ($checks as $check) {
            echo "<div class='check-item {$check['status']}'>";
            echo "<div class='status'>";
            echo $check['status'] === 'success' ? '✅' : ($check['status'] === 'error' ? '❌' : '⚠️');
            echo "</div>";
            echo "<div class='check-content'>";
            echo "<div class='check-title'>{$check['title']}</div>";
            echo "<div class='check-desc'>{$check['desc']}</div>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";

        // Browser support check (JavaScript)
        ?>
        
        <div class="section">
            <h2>🌐 Soporte del Navegador (JavaScript)</h2>
            <div id="browser-checks"></div>
        </div>

        <div class="section">
            <h2>🚀 Próximos Pasos</h2>
            <?php if ($errors > 0 || $warnings > 0): ?>
                <p style="color: #FFC107; margin-bottom: 16px;">
                    ⚠️ Hay problemas que requieren atención. Por favor ejecuta la migración SQL si aún no lo has hecho.
                </p>
                <div class="code">mysql -u root -p thesocialmask < database/migrations/FIX_passkey_authentication.sql</div>
            <?php else: ?>
                <p style="color: #28A745; margin-bottom: 16px;">
                    ✅ ¡Todo configurado correctamente! Puedes probar el sistema de passkeys.
                </p>
            <?php endif; ?>
            
            <a href="/register" class="btn">Probar Registro con Passkey</a>
            <a href="/login" class="btn" style="background: #21262D; color: #C9D1D9;">Probar Login con Passkey</a>
        </div>
    </div>

    <script>
        // Verificar soporte del navegador
        async function checkBrowserSupport() {
            const browserChecks = document.getElementById('browser-checks');
            const checks = [];

            // 1. PublicKeyCredential API
            if (window.PublicKeyCredential) {
                checks.push({
                    status: 'success',
                    title: 'WebAuthn API (PublicKeyCredential)',
                    desc: '✅ Tu navegador soporta WebAuthn.'
                });
            } else {
                checks.push({
                    status: 'error',
                    title: 'WebAuthn API (PublicKeyCredential)',
                    desc: '❌ Tu navegador NO soporta WebAuthn. Usa Chrome 67+, Safari 13+, Firefox 60+, o Edge 18+.'
                });
            }

            // 2. Platform Authenticator
            if (typeof PublicKeyCredential !== 'undefined' && 
                typeof PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable === 'function') {
                try {
                    const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                    if (available) {
                        checks.push({
                            status: 'success',
                            title: 'Platform Authenticator (Touch ID / Face ID / Windows Hello)',
                            desc: '✅ Tu dispositivo tiene autenticador de plataforma disponible.'
                        });
                    } else {
                        checks.push({
                            status: 'warning',
                            title: 'Platform Authenticator',
                            desc: '⚠️ No se detectó autenticador de plataforma, pero puedes usar security keys USB/NFC.'
                        });
                    }
                } catch (error) {
                    checks.push({
                        status: 'warning',
                        title: 'Platform Authenticator',
                        desc: '⚠️ No se pudo verificar: ' + error.message
                    });
                }
            }

            // 3. Conditional Mediation (Autofill)
            if (typeof PublicKeyCredential !== 'undefined' && 
                typeof PublicKeyCredential.isConditionalMediationAvailable === 'function') {
                try {
                    const available = await PublicKeyCredential.isConditionalMediationAvailable();
                    if (available) {
                        checks.push({
                            status: 'success',
                            title: 'Autofill de Passkeys',
                            desc: '✅ Tu navegador soporta autofill de passkeys en campos de login.'
                        });
                    } else {
                        checks.push({
                            status: 'warning',
                            title: 'Autofill de Passkeys',
                            desc: '⚠️ Autofill no disponible, pero passkeys funcionarán normalmente.'
                        });
                    }
                } catch (error) {
                    checks.push({
                        status: 'warning',
                        title: 'Autofill de Passkeys',
                        desc: '⚠️ No se pudo verificar: ' + error.message
                    });
                }
            }

            // 4. Secure Context (HTTPS)
            if (window.isSecureContext) {
                checks.push({
                    status: 'success',
                    title: 'Contexto Seguro (HTTPS)',
                    desc: '✅ La página está servida sobre HTTPS o localhost.'
                });
            } else {
                checks.push({
                    status: 'error',
                    title: 'Contexto Seguro (HTTPS)',
                    desc: '❌ WebAuthn requiere HTTPS en producción. Usa localhost para desarrollo.'
                });
            }

            // Renderizar checks
            checks.forEach(check => {
                const div = document.createElement('div');
                div.className = `check-item ${check.status}`;
                div.innerHTML = `
                    <div class="status">${check.status === 'success' ? '✅' : (check.status === 'error' ? '❌' : '⚠️')}</div>
                    <div class="check-content">
                        <div class="check-title">${check.title}</div>
                        <div class="check-desc">${check.desc}</div>
                    </div>
                `;
                browserChecks.appendChild(div);
            });

            // Información adicional del navegador
            const infoDiv = document.createElement('div');
            infoDiv.className = 'code';
            infoDiv.style.marginTop = '16px';
            infoDiv.innerHTML = `
                <strong>Información del Navegador:</strong><br>
                User Agent: ${navigator.userAgent}<br>
                Plataforma: ${navigator.platform}<br>
                Idioma: ${navigator.language}
            `;
            browserChecks.appendChild(infoDiv);
        }

        // Ejecutar al cargar
        checkBrowserSupport();
    </script>
</body>
</html>
