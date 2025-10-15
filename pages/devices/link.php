<?php
/**
 * ============================================
 * PÁGINA DE VINCULACIÓN DE DISPOSITIVO
 * ============================================
 * Esta página se abre desde el dispositivo NUEVO
 * que quieres vincular a tu cuenta
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vincular Dispositivo - The Social Mask</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .code-input {
            width: 3.5rem;
            height: 4rem;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            background: #161B22;
            border: 2px solid #30363D;
            border-radius: 0.5rem;
            color: #3B82F6;
            transition: all 0.3s;
        }

        .code-input:focus {
            border-color: #3B82F6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .code-input.filled {
            border-color: #10B981;
            background: #161B22;
        }

        .code-input.error {
            border-color: #EF4444;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-2xl w-full">
        
        <!-- Header -->
        <div class="text-center mb-8 animate-fade-in-up">
            <div class="mb-4">
                <i class="fas fa-mobile-screen-button text-6xl text-blue-500"></i>
            </div>
            <h1 class="text-4xl font-bold mb-2">Vincular Dispositivo</h1>
            <p class="text-gray-400">Ingresa el código de 8 dígitos generado desde tu otro dispositivo</p>
        </div>

        <!-- Formulario de Código -->
        <div id="code-form" class="bg-gray-800 rounded-xl p-8 border border-gray-700 animate-fade-in-up">
            
            <!-- Inputs de Código -->
            <div class="mb-8">
                <label class="block text-sm font-semibold text-gray-400 mb-4 text-center uppercase tracking-wider">
                    Código de Vinculación
                </label>
                <div class="flex justify-center gap-2 mb-2" id="code-inputs-container">
                    <input type="text" maxlength="1" class="code-input" data-index="0" autocomplete="off" inputmode="numeric">
                    <input type="text" maxlength="1" class="code-input" data-index="1" autocomplete="off" inputmode="numeric">
                    <input type="text" maxlength="1" class="code-input" data-index="2" autocomplete="off" inputmode="numeric">
                    <input type="text" maxlength="1" class="code-input" data-index="3" autocomplete="off" inputmode="numeric">
                    <div class="flex items-center justify-center px-2">
                        <span class="text-3xl text-blue-500 font-bold">-</span>
                    </div>
                    <input type="text" maxlength="1" class="code-input" data-index="4" autocomplete="off" inputmode="numeric">
                    <input type="text" maxlength="1" class="code-input" data-index="5" autocomplete="off" inputmode="numeric">
                    <input type="text" maxlength="1" class="code-input" data-index="6" autocomplete="off" inputmode="numeric">
                    <input type="text" maxlength="1" class="code-input" data-index="7" autocomplete="off" inputmode="numeric">
                </div>
                <p class="text-xs text-gray-500 text-center">Formato: XXXX-XXXX</p>
            </div>

            <!-- Nombre del Dispositivo -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-400 mb-2">
                    <i class="fas fa-tag mr-2"></i>
                    Nombre del Dispositivo (opcional)
                </label>
                <input 
                    type="text" 
                    id="device-name" 
                    placeholder="Ej: iPhone de Juan, PC Casa, etc."
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none transition">
            </div>

            <!-- Botón Vincular -->
            <button 
                id="link-button"
                onclick="linkDevice()"
                disabled
                class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-700 disabled:cursor-not-allowed text-white font-bold py-4 rounded-lg text-lg transition-all transform hover:scale-105 disabled:transform-none disabled:hover:scale-100 shadow-lg">
                <i class="fas fa-link mr-2"></i>
                <span id="button-text">Vincular Dispositivo</span>
            </button>

            <!-- Mensaje de Error -->
            <div id="error-message" class="hidden mt-4 bg-red-900 bg-opacity-50 border border-red-500 rounded-lg p-4 text-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span id="error-text"></span>
            </div>

            <!-- Intentos Restantes -->
            <div id="attempts-info" class="hidden mt-4 text-center text-sm">
                <span class="text-yellow-500">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    Intentos restantes: <span id="attempts-count">3</span>/3
                </span>
            </div>
        </div>

        <!-- Resultado Exitoso -->
        <div id="success-section" class="hidden bg-gray-800 rounded-xl p-8 border border-green-500 text-center animate-fade-in-up">
            <div class="text-6xl mb-6">🎉</div>
            <h2 class="text-3xl font-bold text-green-500 mb-4">¡Dispositivo Vinculado!</h2>
            <p class="text-gray-400 mb-6">Este dispositivo se ha vinculado exitosamente a tu cuenta</p>
            <div class="bg-gray-900 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-400 mb-2">Cuenta vinculada:</p>
                <p class="text-xl font-bold" id="success-username"></p>
                <p class="text-sm text-gray-500" id="success-email"></p>
            </div>
            <button 
                onclick="window.location.href='/pages/dashboard.php'"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
                <i class="fas fa-home mr-2"></i>
                Ir al Dashboard
            </button>
        </div>

        <!-- Información de Seguridad -->
        <div class="mt-8 bg-gray-800 bg-opacity-50 rounded-lg p-6 border border-gray-700">
            <h3 class="font-bold mb-3 flex items-center gap-2">
                <i class="fas fa-shield-halved text-blue-500"></i>
                Seguridad
            </h3>
            <ul class="text-sm text-gray-400 space-y-2">
                <li>✓ El código es válido solo por <strong>5 minutos</strong></li>
                <li>✓ Solo tienes <strong>3 intentos</strong> para ingresar el código correcto</li>
                <li>✓ Tu dispositivo quedará identificado por una huella única</li>
                <li>✓ Puedes revocar el acceso en cualquier momento desde tu panel</li>
            </ul>
        </div>

        <!-- Link para generar código -->
        <div class="mt-6 text-center text-sm text-gray-500">
            ¿No tienes un código? 
            <a href="/pages/devices/manage.php" class="text-blue-500 hover:text-blue-400 transition">
                Genera uno aquí
            </a>
        </div>
    </div>

    <script src="/assets/js/device-linking.js"></script>
    <script>
        // ============================================
        // CODE INPUT MANAGEMENT
        // ============================================
        const codeInputs = document.querySelectorAll('.code-input');
        const linkButton = document.getElementById('link-button');
        let attemptsRemaining = 3;

        // Auto-focus y navegación entre inputs
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                
                // Solo permitir números
                if (!/^\d$/.test(value) && value !== '') {
                    e.target.value = '';
                    return;
                }

                // Marcar como lleno
                if (value) {
                    e.target.classList.add('filled');
                    
                    // Mover al siguiente input
                    if (index < codeInputs.length - 1) {
                        codeInputs[index + 1].focus();
                    }
                } else {
                    e.target.classList.remove('filled');
                }

                // Verificar si el código está completo
                checkCodeComplete();
            });

            // Backspace navigation
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                    codeInputs[index - 1].value = '';
                    codeInputs[index - 1].classList.remove('filled');
                    checkCodeComplete();
                }
            });

            // Paste handling
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text');
                const digits = pastedData.replace(/\D/g, '').split('');
                
                digits.forEach((digit, i) => {
                    if (i < codeInputs.length) {
                        codeInputs[i].value = digit;
                        codeInputs[i].classList.add('filled');
                    }
                });
                
                checkCodeComplete();
                if (digits.length >= codeInputs.length) {
                    codeInputs[codeInputs.length - 1].focus();
                }
            });
        });

        // Auto-focus primer input
        codeInputs[0].focus();

        function checkCodeComplete() {
            const code = Array.from(codeInputs).map(input => input.value).join('');
            linkButton.disabled = code.length !== 8;
            
            // Auto-submit si está completo (opcional)
            if (code.length === 8) {
                // linkDevice(); // Descomentar para auto-submit
            }
        }

        function getCode() {
            return Array.from(codeInputs).map(input => input.value).join('');
        }

        function clearCode() {
            codeInputs.forEach(input => {
                input.value = '';
                input.classList.remove('filled', 'error');
            });
            codeInputs[0].focus();
        }

        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');
            errorText.textContent = message;
            errorDiv.classList.remove('hidden');
            
            // Shake animation
            codeInputs.forEach(input => {
                input.classList.add('error');
            });
            
            setTimeout(() => {
                codeInputs.forEach(input => {
                    input.classList.remove('error');
                });
            }, 500);

            // Actualizar intentos
            attemptsRemaining--;
            if (attemptsRemaining > 0) {
                document.getElementById('attempts-info').classList.remove('hidden');
                document.getElementById('attempts-count').textContent = attemptsRemaining;
            } else {
                linkButton.disabled = true;
                errorText.textContent = 'Has agotado todos los intentos. El código está bloqueado.';
            }
        }

        function hideError() {
            document.getElementById('error-message').classList.add('hidden');
        }

        // ============================================
        // LINK DEVICE
        // ============================================
        async function linkDevice() {
            const code = getCode();
            const deviceName = document.getElementById('device-name').value || 
                              `${getBrowserName()} - ${new Date().toLocaleDateString()}`;
            
            hideError();
            
            // Cambiar botón a loading
            linkButton.disabled = true;
            document.getElementById('button-text').innerHTML = 
                '<i class="fas fa-spinner fa-spin mr-2"></i>Vinculando...';
            
            try {
                // Generar fingerprint del dispositivo
                const deviceFingerprint = await DeviceFingerprint.generate();
                
                // Enviar al servidor
                const response = await fetch('/api/devices/verify-link-code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        code: code,
                        device_fingerprint: deviceFingerprint,
                        device_name: deviceName
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Guardar token del dispositivo
                    localStorage.setItem('device_token', data.device_token);
                    localStorage.setItem('device_id', data.device_id);
                    
                    // Mostrar success
                    document.getElementById('code-form').classList.add('hidden');
                    document.getElementById('success-section').classList.remove('hidden');
                    document.getElementById('success-username').textContent = data.user.username;
                    document.getElementById('success-email').textContent = data.user.email;
                    
                    // Redirigir después de 3 segundos
                    setTimeout(() => {
                        window.location.href = '/pages/dashboard.php';
                    }, 3000);
                } else {
                    showError(data.error || 'Código inválido');
                    clearCode();
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error de conexión. Intenta nuevamente.');
            } finally {
                linkButton.disabled = false;
                document.getElementById('button-text').innerHTML = 
                    '<i class="fas fa-link mr-2"></i>Vincular Dispositivo';
            }
        }

        function getBrowserName() {
            const ua = navigator.userAgent;
            if (ua.includes('Firefox')) return 'Firefox';
            if (ua.includes('Chrome')) return 'Chrome';
            if (ua.includes('Safari')) return 'Safari';
            if (ua.includes('Edge')) return 'Edge';
            return 'Navegador';
        }

        // Enter key to submit
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !linkButton.disabled) {
                linkDevice();
            }
        });
    </script>
</body>
</html>
