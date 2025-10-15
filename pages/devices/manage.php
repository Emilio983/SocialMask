<?php
/**
 * ============================================
 * PANEL DE GESTIÓN DE DISPOSITIVOS
 * ============================================
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/utils.php';

if (!isAuthenticated()) {
    header('Location: /pages/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT username, email FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Dispositivos - The Social Mask</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse-ring {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.5; }
        }
        .code-digit {
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
            width: 3.5rem;
            height: 4rem;
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            background: #161B22;
            border: 2px solid #30363D;
            border-radius: 0.5rem;
            color: #3B82F6;
            display: flex;
            align-items: center;
            justify-center;
        }
        .code-separator {
            font-size: 2rem;
            color: #3B82F6;
            font-weight: 700;
            padding: 0 0.5rem;
        }
        .pulse-ring {
            animation: pulse-ring 2s infinite;
        }
        .device-card {
            transition: all 0.3s;
        }
        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.2);
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    
    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        
        <div class="mb-8">
            <h1 class="text-4xl font-bold mb-2 flex items-center gap-3">
                <i class="fas fa-mobile-alt text-blue-500"></i>
                Mis Dispositivos
            </h1>
            <p class="text-gray-400">Gestiona los dispositivos vinculados a tu cuenta</p>
        </div>

        <div class="bg-blue-900 bg-opacity-20 border border-blue-500 rounded-lg p-6 mb-8">
            <div class="flex gap-4">
                <i class="fas fa-shield-alt text-blue-500 text-3xl"></i>
                <div>
                    <h3 class="text-lg font-bold mb-2">🔐 Sistema de Vinculación Segura</h3>
                    <ul class="text-sm text-gray-300 space-y-1">
                        <li>✅ Los códigos expiran automáticamente en <strong>5 minutos</strong></li>
                        <li>✅ Solo puedes tener <strong>1 código activo</strong> a la vez</li>
                        <li>✅ Máximo <strong>3 intentos</strong> por código</li>
                        <li>✅ Cada dispositivo tiene una <strong>huella única</strong></li>
                        <li>✅ Todos los intentos quedan <strong>registrados</strong></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Panel de Generación de Código -->
            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                    <i class="fas fa-link text-green-500"></i>
                    Vincular Nuevo Dispositivo
                </h2>

                <div id="generate-section" class="text-center py-8">
                    <div class="mb-6">
                        <i class="fas fa-qrcode text-gray-600 text-6xl mb-4"></i>
                        <p class="text-gray-400 mb-6">
                            Genera un código temporal para vincular un nuevo dispositivo
                        </p>
                    </div>
                    
                    <button 
                        onclick="deviceLinkingManager.generateCode()"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-lg text-lg transition-all transform hover:scale-105 shadow-lg">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Generar Código
                    </button>

                    <div class="mt-6 text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-2"></i>
                        El código será válido por 5 minutos
                    </div>
                </div>

                <div id="code-section" class="hidden">
                    <div class="bg-gray-900 rounded-xl p-8 text-center border-2 border-blue-500 pulse-ring">
                        <div class="mb-4">
                            <span class="text-sm text-gray-400 uppercase tracking-wider">Código de Vinculación</span>
                        </div>
                        
                        <div id="code-display" class="flex justify-center items-center gap-2 mb-6"></div>

                        <div class="mb-6">
                            <div class="flex items-center justify-center gap-2 mb-2">
                                <i class="fas fa-clock text-blue-500"></i>
                                <span class="text-sm text-gray-400">Expira en:</span>
                                <span id="timer" class="text-2xl font-bold text-blue-500"></span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div id="timer-progress" class="bg-blue-500 h-2 rounded-full transition-all duration-1000" style="width: 100%"></div>
                            </div>
                        </div>

                        <div class="bg-gray-800 rounded-lg p-4 mb-4 text-left">
                            <p class="text-sm text-gray-300 mb-3 font-semibold">📱 En tu otro dispositivo:</p>
                            <ol class="text-sm text-gray-400 space-y-2 list-decimal list-inside">
                                <li>Abre tu navegador</li>
                                <li>Ve a: <code class="bg-gray-700 px-2 py-1 rounded text-blue-400">
                                    <?php echo $_SERVER['HTTP_HOST']; ?>/pages/devices/link.php
                                </code></li>
                                <li>Ingresa este código</li>
                                <li>Tu dispositivo quedará vinculado</li>
                            </ol>
                        </div>

                        <div class="flex gap-3">
                            <button 
                                onclick="deviceLinkingManager.copyCode()"
                                class="flex-1 bg-gray-700 hover:bg-gray-600 text-white py-3 rounded-lg font-semibold transition">
                                <i class="fas fa-copy mr-2"></i>
                                Copiar
                            </button>
                            <button 
                                onclick="deviceLinkingManager.cancelCode()"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-semibold transition">
                                <i class="fas fa-times mr-2"></i>
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Dispositivos -->
            <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
                    <i class="fas fa-laptop text-blue-500"></i>
                    Dispositivos Vinculados
                </h2>

                <div id="devices-list" class="space-y-4">
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-spinner fa-spin text-4xl mb-4"></i>
                        <p>Cargando dispositivos...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="global-loader" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center">
        <div class="bg-gray-800 rounded-xl p-8 text-center">
            <i class="fas fa-spinner fa-spin text-5xl text-blue-500 mb-4"></i>
            <p class="loader-text text-xl">Cargando...</p>
        </div>
    </div>

    <script src="/assets/js/toast-notifications.js"></script>
    <script src="/assets/js/device-linking.js"></script>
    <script>
        async function loadDevices() {
            try {
                const response = await fetch('/api/devices/list_devices.php', {
                    credentials: 'include'
                });
                
                const data = await response.json();
                const container = document.getElementById('devices-list');
                container.innerHTML = '';
                
                if (data.success && data.devices && data.devices.length > 0) {
                    data.devices.forEach(device => {
                        container.appendChild(createDeviceCard(device));
                    });
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p>No hay dispositivos vinculados</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error cargando dispositivos:', error);
            }
        }

        function createDeviceCard(device) {
            const card = document.createElement('div');
            card.className = 'device-card bg-gray-900 rounded-lg p-4 border border-gray-700 hover:border-blue-500 transition-all';
            
            const isCurrentDevice = device.is_current;
            const statusColor = device.status === 'active' ? 'text-green-500' : 'text-red-500';
            
            card.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="text-4xl">${getDeviceIcon(device.user_agent)}</div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-bold text-lg">${escapeHtml(device.device_name || 'Dispositivo')}</h3>
                                ${isCurrentDevice ? '<span class="text-xs bg-blue-600 px-2 py-1 rounded">Este dispositivo</span>' : ''}
                            </div>
                            <p class="text-sm text-gray-400">
                                <i class="fas fa-calendar mr-1"></i>
                                Vinculado: ${formatDate(device.created_at)}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="${statusColor} mb-2">
                            <i class="fas fa-circle text-xs"></i>
                            ${device.status}
                        </div>
                        ${!isCurrentDevice ? `
                            <button onclick="revokeDevice(${device.id})" class="text-red-500 hover:text-red-400 text-sm">
                                <i class="fas fa-trash mr-1"></i>
                                Revocar
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
            
            return card;
        }

        function getDeviceIcon(userAgent) {
            if (!userAgent) return '💻';
            const ua = userAgent.toLowerCase();
            if (ua.includes('mobile') || ua.includes('android') || ua.includes('iphone')) return '📱';
            return '💻';
        }

        function formatDate(dateString) {
            if (!dateString) return 'Nunca';
            return new Date(dateString).toLocaleString('es-ES');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function revokeDevice(deviceId) {
            if (!confirm('¿Revocar este dispositivo?')) return;
            
            try {
                const response = await fetch('/api/devices/revoke_device.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ device_id: deviceId })
                });
                
                const data = await response.json();
                if (data.success) {
                    showToast('✅ Dispositivo revocado', 'success');
                    loadDevices();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        document.addEventListener('DOMContentLoaded', loadDevices);
    </script>
</body>
</html>
