<?php
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/utils.php';

if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT username FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mis Dispositivos - TheSocialMask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-bg-primary': '#0D1117',
                        'brand-bg-secondary': '#161B22',
                        'brand-border': '#30363D',
                        'brand-text-primary': '#C9D1D9',
                        'brand-text-secondary': '#8B949E',
                        'brand-accent': '#3B82F6',
                        'brand-success': '#28A745',
                        'brand-error': '#DC3545',
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                }
            }
        };
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0D1117;
        }

        .code-digit {
            width: 2.5rem;
            height: 3rem;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            background: #161B22;
            border: 2px solid #30363D;
            border-radius: 0.5rem;
            color: #3B82F6;
            transition: all 0.3s;
        }

        .code-digit:hover {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }

        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }

        .pulse-animation {
            animation: pulse-ring 2s infinite;
        }

        .device-card {
            transition: all 0.3s;
        }

        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">
    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-12">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-brand-text-primary mb-2">
                <i class="fas fa-shield-alt text-brand-accent mr-3"></i>
                Autenticación de Dos Factores
            </h1>
            <p class="text-brand-text-secondary text-base sm:text-lg">
                Gestiona tus dispositivos autorizados y códigos de seguridad
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: 2FA Code & Pending Requests -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Current 2FA Code Card -->
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-brand-text-primary">
                            <i class="fas fa-key text-brand-accent mr-2"></i>
                            Tu Código de Seguridad
                        </h2>
                        <span class="status-badge status-active">
                            <i class="fas fa-circle text-xs mr-1"></i>
                            Activo
                        </span>
                    </div>

                    <p class="text-brand-text-secondary mb-6">
                        Usa este código para aprobar nuevos inicios de sesión desde otros dispositivos
                    </p>

                    <!-- 6-Digit Code Display -->
                    <div id="codeDisplay" class="flex justify-center gap-2 mb-4">
                        <div class="code-digit">-</div>
                        <div class="code-digit">-</div>
                        <div class="code-digit">-</div>
                        <div class="code-digit">-</div>
                        <div class="code-digit">-</div>
                        <div class="code-digit">-</div>
                    </div>

                    <!-- Copy Button -->
                    <div class="flex justify-center mb-6">
                        <button onclick="copyCodeToClipboard()" class="bg-brand-accent/20 hover:bg-brand-accent/30 text-brand-accent px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2">
                            <i class="fas fa-copy"></i>
                            <span id="copyButtonText">Copiar Código</span>
                        </button>
                    </div>

                    <!-- Timer & Refresh -->
                    <div class="flex items-center justify-between p-4 bg-brand-bg-primary rounded-lg border border-brand-border">
                        <div class="flex items-center space-x-3">
                            <div class="w-3 h-3 bg-brand-success rounded-full pulse-animation"></div>
                            <span class="text-sm text-brand-text-secondary">
                                El código expira en: <span id="timeRemaining" class="font-semibold text-brand-text-primary">--:--</span>
                            </span>
                        </div>
                        <button onclick="refreshCode()" class="text-brand-accent hover:text-blue-400 transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Actualizar
                        </button>
                    </div>
                </div>

                <!-- Authorize New Device with Code -->
                <div class="bg-brand-bg-secondary border border-brand-accent rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-brand-text-primary">
                            <i class="fas fa-mobile-alt text-brand-accent mr-2"></i>
                            Autorizar Nuevo Dispositivo
                        </h2>
                        <span class="status-badge status-active">
                            <i class="fas fa-shield-alt text-xs mr-1"></i>
                            Seguro
                        </span>
                    </div>

                    <p class="text-brand-text-secondary mb-6">
                        Ingresa el código de 6 dígitos que generaste desde el nuevo dispositivo
                    </p>

                    <!-- 6-Digit Code Input -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-brand-text-primary mb-3 text-center">
                            Código de Autorización
                        </label>
                        <div class="flex justify-center gap-2 mb-4" id="auth-code-inputs">
                            <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="auth-code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="0" autocomplete="off">
                            <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="auth-code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="1" autocomplete="off">
                            <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="auth-code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="2" autocomplete="off">
                            <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="auth-code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="3" autocomplete="off">
                            <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="auth-code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="4" autocomplete="off">
                            <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="auth-code-input w-12 h-14 text-center text-2xl font-bold bg-brand-bg-primary border-2 border-brand-border text-brand-accent rounded-lg focus:border-brand-accent focus:ring-2 focus:ring-brand-accent focus:ring-opacity-50 outline-none transition-all" data-index="5" autocomplete="off">
                        </div>

                        <!-- Error Message -->
                        <div id="auth-code-error" class="hidden text-brand-error text-sm text-center mb-3">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <span id="auth-error-text">Código incorrecto</span>
                        </div>
                    </div>

                    <!-- Verify Button -->
                    <button id="verify-auth-code-btn" class="w-full bg-brand-accent hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-check mr-2"></i>
                        Verificar y Autorizar
                    </button>

                    <!-- Help Text -->
                    <div class="mt-4 text-center text-xs text-brand-text-secondary">
                        <i class="fas fa-info-circle mr-1"></i>
                        El código expira en 10 minutos después de generarse
                    </div>
                </div>

                <!-- Pending Login Requests -->
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-brand-text-primary">
                            <i class="fas fa-bell text-yellow-500 mr-2"></i>
                            Solicitudes Pendientes
                        </h2>
                        <span id="pendingCount" class="status-badge status-pending">
                            0 pendientes
                        </span>
                    </div>

                    <div id="pendingRequests" class="space-y-4">
                        <div class="text-center py-8 text-brand-text-secondary">
                            <i class="fas fa-check-circle text-4xl mb-3 opacity-50"></i>
                            <p>No hay solicitudes pendientes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Authorized Devices -->
            <div class="lg:col-span-1">
                <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-brand-text-primary">
                            <i class="fas fa-laptop text-brand-accent mr-2"></i>
                            Dispositivos
                        </h2>
                        <button onclick="showAddDeviceModal()" class="text-sm bg-brand-accent hover:bg-blue-600 text-white px-3 py-1 rounded transition-colors">
                            <i class="fas fa-plus mr-1"></i>
                            Añadir
                        </button>
                    </div>

                    <div id="devicesList" class="space-y-3">
                        <div class="text-center py-8 text-brand-text-secondary">
                            <i class="fas fa-spinner fa-spin text-2xl mb-3"></i>
                            <p class="text-sm">Cargando dispositivos...</p>
                        </div>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="bg-blue-900 bg-opacity-20 border border-blue-800 rounded-lg p-4 mt-6">
                    <h3 class="font-semibold text-brand-text-primary mb-2 flex items-center">
                        <i class="fas fa-info-circle text-brand-accent mr-2"></i>
                        ¿Cómo funciona?
                    </h3>
                    <ul class="text-sm text-brand-text-secondary space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-check text-brand-success mr-2 mt-1"></i>
                            <span>El código cambia automáticamente cada 10 minutos</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-brand-success mr-2 mt-1"></i>
                            <span>Usa el código para aprobar nuevos dispositivos</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-brand-success mr-2 mt-1"></i>
                            <span>Rechaza solicitudes sospechosas inmediatamente</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Device Modal -->
    <div id="addDeviceModal" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-brand-bg-secondary border border-brand-border rounded-lg max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-semibold text-brand-text-primary">Añadir Dispositivo</h3>
                <button onclick="closeAddDeviceModal()" class="text-brand-text-secondary hover:text-brand-text-primary">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <p class="text-brand-text-secondary mb-4">
                Este dispositivo será registrado como autorizado para tu cuenta.
            </p>

            <div class="mb-4">
                <label class="block text-sm font-medium text-brand-text-primary mb-2">
                    Nombre del Dispositivo
                </label>
                <input
                    type="text"
                    id="deviceName"
                    placeholder="Ej: Mi Laptop Personal"
                    class="w-full bg-brand-bg-primary border border-brand-border text-brand-text-primary rounded-lg px-4 py-2 focus:ring-2 focus:ring-brand-accent focus:border-transparent"
                >
            </div>

            <div class="flex gap-3">
                <button onclick="addCurrentDevice()" class="flex-1 bg-brand-accent hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Añadir
                </button>
                <button onclick="closeAddDeviceModal()" class="flex-1 bg-brand-bg-primary border border-brand-border text-brand-text-primary hover:bg-brand-border px-4 py-2 rounded-lg font-semibold transition-colors">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- Notification System -->
    <script src="/assets/js/notifications.js"></script>

    <script>
        let currentCode = null;
        let codeTimer = null;
        let pendingRequestsInterval = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadCurrentCode();
            loadDevices();
            loadPendingRequests();

            // Poll for pending requests every 10 seconds
            pendingRequestsInterval = setInterval(loadPendingRequests, 10000);
        });

        // Load current 2FA code
        async function loadCurrentCode() {
            try {
                const response = await fetch('/api/2fa/get-code.php');
                const data = await response.json();

                if (data.success) {
                    currentCode = data.data;
                    displayCode(currentCode.code);
                    startTimer(currentCode.expires_in_seconds);
                } else {
                    console.error('Failed to load code:', data.error);
                }
            } catch (error) {
                console.error('Error loading code:', error);
            }
        }

        // Display code in digit boxes
        function displayCode(code) {
            const digits = code.split('');
            const digitElements = document.querySelectorAll('.code-digit');
            digits.forEach((digit, index) => {
                if (digitElements[index]) {
                    digitElements[index].textContent = digit;
                }
            });
        }

        // Start countdown timer
        function startTimer(seconds) {
            if (codeTimer) clearInterval(codeTimer);

            let remaining = seconds;
            updateTimerDisplay(remaining);

            codeTimer = setInterval(() => {
                remaining--;
                updateTimerDisplay(remaining);

                if (remaining <= 0) {
                    clearInterval(codeTimer);
                    refreshCode();
                }
            }, 1000);
        }

        // Update timer display
        function updateTimerDisplay(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            document.getElementById('timeRemaining').textContent =
                `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // Refresh code manually
        async function refreshCode() {
            await loadCurrentCode();
            if (typeof window.notify !== 'undefined') {
                window.notify.info('Código actualizado correctamente', 'Código Renovado', 3000);
            }
        }

        // Copy code to clipboard
        async function copyCodeToClipboard() {
            if (!currentCode) {
                if (typeof window.notify !== 'undefined') {
                    window.notify.warning('No hay código disponible para copiar', 'Sin Código', 3000);
                }
                return;
            }

            try {
                await navigator.clipboard.writeText(currentCode.code);
                const btn = document.getElementById('copyButtonText');
                const originalText = btn.textContent;
                btn.innerHTML = '<i class="fas fa-check mr-1"></i> ¡Copiado!';

                if (typeof window.notify !== 'undefined') {
                    window.notify.success('El código ha sido copiado al portapapeles', 'Código Copiado', 3000);
                }

                setTimeout(() => {
                    btn.textContent = originalText;
                }, 2000);
            } catch (error) {
                console.error('Error copying code:', error);
                if (typeof window.notify !== 'undefined') {
                    window.notify.error('No se pudo copiar el código. Inténtalo manualmente.', 'Error al Copiar', 4000);
                }
            }
        }

        // Load authorized devices
        async function loadDevices() {
            try {
                const response = await fetch('/api/2fa/list-devices.php');
                const data = await response.json();

                if (data.success) {
                    displayDevices(data.devices);
                } else {
                    console.error('Failed to load devices:', data.error);
                }
            } catch (error) {
                console.error('Error loading devices:', error);
            }
        }

        // Display devices list
        function displayDevices(devices) {
            const container = document.getElementById('devicesList');

            if (devices.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8 text-brand-text-secondary">
                        <i class="fas fa-laptop text-3xl mb-3 opacity-50"></i>
                        <p class="text-sm">No hay dispositivos registrados</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = devices.map(device => `
                <div class="device-card bg-brand-bg-primary border border-brand-border rounded-lg p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center">
                            <i class="fas fa-laptop text-brand-accent mr-2"></i>
                            <span class="font-semibold text-brand-text-primary text-sm">
                                ${device.device_name || 'Dispositivo sin nombre'}
                            </span>
                        </div>
                        <button onclick="removeDevice(${device.device_id})" class="text-brand-error hover:text-red-400 text-xs">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="text-xs text-brand-text-secondary space-y-1">
                        <div><i class="fas fa-globe mr-1"></i> ${device.ip_address}</div>
                        <div><i class="fas fa-clock mr-1"></i> ${formatDate(device.last_used_at)}</div>
                    </div>
                </div>
            `).join('');
        }

        // Load pending login requests
        async function loadPendingRequests() {
            try {
                const response = await fetch('/api/2fa/pending-requests.php');
                const data = await response.json();

                if (data.success) {
                    displayPendingRequests(data.requests);
                }
            } catch (error) {
                console.error('Error loading pending requests:', error);
            }
        }

        // Display pending requests
        function displayPendingRequests(requests) {
            const container = document.getElementById('pendingRequests');
            const countBadge = document.getElementById('pendingCount');

            countBadge.textContent = `${requests.length} pendiente${requests.length !== 1 ? 's' : ''}`;

            if (requests.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8 text-brand-text-secondary">
                        <i class="fas fa-check-circle text-4xl mb-3 opacity-50"></i>
                        <p>No hay solicitudes pendientes</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = requests.map(request => `
                <div class="bg-brand-bg-primary border border-yellow-800 rounded-lg p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="font-semibold text-brand-text-primary mb-1">
                                Nuevo inicio de sesión
                            </div>
                            <div class="text-xs text-brand-text-secondary space-y-1">
                                <div><i class="fas fa-globe mr-1"></i> ${request.ip_address}</div>
                                <div><i class="fas fa-clock mr-1"></i> ${formatDate(request.created_at)}</div>
                            </div>
                        </div>
                        <span class="status-badge status-pending">Pendiente</span>
                    </div>

                    <div class="flex gap-2">
                        <button onclick="approveRequest(${request.request_id})" class="flex-1 bg-brand-success hover:bg-green-600 text-white px-3 py-2 rounded text-sm font-semibold transition-colors">
                            <i class="fas fa-check mr-1"></i> Aprobar
                        </button>
                        <button onclick="rejectRequest(${request.request_id})" class="flex-1 bg-brand-error hover:bg-red-600 text-white px-3 py-2 rounded text-sm font-semibold transition-colors">
                            <i class="fas fa-times mr-1"></i> Rechazar
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Approve login request
        async function approveRequest(requestId) {
            if (!currentCode) {
                if (typeof window.notify !== 'undefined') {
                    window.notify.error('No se pudo obtener el código de seguridad actual', 'Error', 5000);
                } else {
                    alert('No se pudo obtener el código de seguridad');
                }
                return;
            }

            // Get current device_id (would need to be stored or retrieved)
            const devices = await fetch('/api/2fa/list-devices.php').then(r => r.json());
            if (!devices.success || devices.devices.length === 0) {
                if (typeof window.notify !== 'undefined') {
                    window.notify.warning('Debes tener al menos un dispositivo autorizado', 'Sin Dispositivos', 5000);
                } else {
                    alert('No tienes dispositivos autorizados');
                }
                return;
            }

            const deviceId = devices.devices[0].device_id;

            try {
                const response = await fetch('/api/2fa/approve-login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: requestId,
                        device_id: deviceId,
                        code: currentCode.code
                    })
                });

                const data = await response.json();

                if (data.success) {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.success('El dispositivo ha sido autorizado exitosamente', '¡Inicio de Sesión Aprobado!', 5000);
                    } else {
                        alert('Inicio de sesión aprobado');
                    }
                    loadPendingRequests();
                } else {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.error(data.error || 'No se pudo aprobar la solicitud', 'Error al Aprobar', 5000);
                    } else {
                        alert('Error: ' + data.error);
                    }
                }
            } catch (error) {
                console.error('Error approving request:', error);
                if (typeof window.notify !== 'undefined') {
                    window.notify.error('No se pudo conectar con el servidor', 'Error de Conexión', 5000);
                } else {
                    alert('Error al aprobar la solicitud');
                }
            }
        }

        // Reject login request
        async function rejectRequest(requestId) {
            const devices = await fetch('/api/2fa/list-devices.php').then(r => r.json());
            if (!devices.success || devices.devices.length === 0) return;

            const deviceId = devices.devices[0].device_id;

            try {
                const response = await fetch('/api/2fa/reject-login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: requestId,
                        device_id: deviceId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.warning('La solicitud de inicio de sesión ha sido rechazada por seguridad', 'Inicio de Sesión Rechazado', 5000);
                    } else {
                        alert('Inicio de sesión rechazado');
                    }
                    loadPendingRequests();
                } else {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.error(data.error || 'No se pudo rechazar la solicitud', 'Error', 5000);
                    } else {
                        alert('Error: ' + data.error);
                    }
                }
            } catch (error) {
                console.error('Error rejecting request:', error);
                if (typeof window.notify !== 'undefined') {
                    window.notify.error('No se pudo conectar con el servidor', 'Error de Conexión', 5000);
                }
            }
        }

        // Show add device modal
        function showAddDeviceModal() {
            document.getElementById('addDeviceModal').classList.remove('hidden');
        }

        // Close add device modal
        function closeAddDeviceModal() {
            document.getElementById('addDeviceModal').classList.add('hidden');
            document.getElementById('deviceName').value = '';
        }

        // Add current device
        async function addCurrentDevice() {
            const deviceName = document.getElementById('deviceName').value.trim() || 'Dispositivo sin nombre';

            // Generate device fingerprint
            const fingerprint = await generateDeviceFingerprint();

            try {
                const response = await fetch('/api/2fa/register-device.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: deviceName,
                        fingerprint: fingerprint,
                        ip: '<?php echo $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; ?>',
                        user_agent: navigator.userAgent
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Store device token in localStorage
                    localStorage.setItem('device_token', data.device_token);
                    closeAddDeviceModal();
                    loadDevices();
                    if (typeof window.notify !== 'undefined') {
                        window.notify.success(`"${deviceName}" ha sido registrado y autorizado correctamente`, '¡Dispositivo Añadido!', 5000);
                    } else {
                        alert('Dispositivo añadido correctamente');
                    }
                } else {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.error(data.error || 'No se pudo registrar el dispositivo', 'Error al Añadir', 5000);
                    } else {
                        alert('Error: ' + data.error);
                    }
                }
            } catch (error) {
                console.error('Error adding device:', error);
                if (typeof window.notify !== 'undefined') {
                    window.notify.error('No se pudo conectar con el servidor', 'Error de Conexión', 5000);
                } else {
                    alert('Error al añadir dispositivo');
                }
            }
        }

        // Remove device
        async function removeDevice(deviceId) {
            if (!confirm('¿Estás seguro de que quieres eliminar este dispositivo?')) {
                return;
            }

            try {
                const response = await fetch('/api/2fa/remove-device.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ device_id: deviceId })
                });

                const data = await response.json();

                if (data.success) {
                    loadDevices();
                    if (typeof window.notify !== 'undefined') {
                        window.notify.success('El dispositivo ha sido eliminado de tu lista de autorizados', 'Dispositivo Eliminado', 4000);
                    } else {
                        alert('Dispositivo eliminado');
                    }
                } else {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.error(data.error || 'No se pudo eliminar el dispositivo', 'Error', 5000);
                    } else {
                        alert('Error: ' + data.error);
                    }
                }
            } catch (error) {
                console.error('Error removing device:', error);
                if (typeof window.notify !== 'undefined') {
                    window.notify.error('No se pudo conectar con el servidor', 'Error de Conexión', 5000);
                }
            }
        }

        // Generate device fingerprint
        async function generateDeviceFingerprint() {
            const components = [
                navigator.userAgent,
                navigator.language,
                screen.colorDepth,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
                navigator.hardwareConcurrency || 'unknown',
                navigator.deviceMemory || 'unknown'
            ];

            const fingerprint = components.join('|');

            // Hash the fingerprint
            const encoder = new TextEncoder();
            const data = encoder.encode(fingerprint);
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        }

        // Format date helper
        function formatDate(dateString) {
            if (!dateString) return 'Nunca';
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Justo ahora';
            if (diffMins < 60) return `Hace ${diffMins} min`;
            if (diffHours < 24) return `Hace ${diffHours}h`;
            if (diffDays < 7) return `Hace ${diffDays}d`;

            return date.toLocaleDateString('es-ES');
        }

        // === Authorization Code Input Handling ===

        // Setup authorization code inputs
        const authInputs = document.querySelectorAll('.auth-code-input');
        authInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;

                // Only allow numbers
                if (!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }

                // Auto-move to next input
                if (value && index < authInputs.length - 1) {
                    authInputs[index + 1].focus();
                }

                // Auto-submit when all filled
                const authCode = getAuthCode();
                if (authCode.length === 6) {
                    verifyAuthorizationCode(authCode);
                }
            });

            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    authInputs[index - 1].focus();
                    authInputs[index - 1].value = '';
                }
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = e.clipboardData.getData('text');
                const digits = paste.replace(/\D/g, '').slice(0, 6);

                digits.split('').forEach((digit, i) => {
                    if (authInputs[i]) {
                        authInputs[i].value = digit;
                    }
                });

                if (digits.length === 6) {
                    verifyAuthorizationCode(digits);
                } else if (digits.length > 0) {
                    authInputs[Math.min(digits.length, authInputs.length - 1)].focus();
                }
            });
        });

        // Verify button click
        document.getElementById('verify-auth-code-btn').addEventListener('click', () => {
            const authCode = getAuthCode();
            if (authCode.length === 6) {
                verifyAuthorizationCode(authCode);
            } else {
                showAuthError('Ingresa los 6 dígitos del código');
            }
        });

        // Get authorization code from inputs
        function getAuthCode() {
            return Array.from(authInputs).map(input => input.value).join('');
        }

        // Clear authorization code inputs
        function clearAuthCode() {
            authInputs.forEach(input => input.value = '');
            authInputs[0].focus();
        }

        // Show authorization error
        function showAuthError(message) {
            const errorEl = document.getElementById('auth-code-error');
            const errorText = document.getElementById('auth-error-text');
            errorText.textContent = message;
            errorEl.classList.remove('hidden');

            // Shake animation
            authInputs.forEach(input => {
                input.classList.add('border-brand-error');
                setTimeout(() => input.classList.remove('border-brand-error'), 500);
            });
        }

        // Hide authorization error
        function hideAuthError() {
            document.getElementById('auth-code-error').classList.add('hidden');
        }

        // Verify authorization code
        async function verifyAuthorizationCode(code) {
            hideAuthError();
            const btn = document.getElementById('verify-auth-code-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando...';

            try {
                const response = await fetch('/api/2fa/validate-login-code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code: code })
                });

                const data = await response.json();

                if (data.success) {
                    if (typeof window.notify !== 'undefined') {
                        window.notify.success('¡Código válido! El nuevo dispositivo ha sido autorizado correctamente.', '¡Autorización Exitosa!', 5000);
                    }
                    clearAuthCode();
                    // Optionally refresh pending requests or show success state
                    setTimeout(() => {
                        loadPendingRequests();
                    }, 1000);
                } else {
                    showAuthError(data.error || 'Código incorrecto o expirado');
                    if (typeof window.notify !== 'undefined') {
                        window.notify.error(data.error || 'El código ingresado no es válido', 'Error de Autorización', 5000);
                    }
                    clearAuthCode();
                }
            } catch (error) {
                console.error('Error verifying authorization code:', error);
                showAuthError('Error de conexión');
                if (typeof window.notify !== 'undefined') {
                    window.notify.error('No se pudo conectar con el servidor', 'Error de Conexión', 5000);
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check mr-2"></i>Verificar y Autorizar';
            }
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (codeTimer) clearInterval(codeTimer);
            if (pendingRequestsInterval) clearInterval(pendingRequestsInterval);
        });
    </script>
</body>
</html>
