<?php
/**
 * SocialMask - Receive Funds Page
 * Página para recibir USDT y SPHE en Polygon Network
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/utils.php';

if (!isAuthenticated()) {
    header('Location: /pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Obtener datos del usuario incluyendo smart account
try {
    $stmt = $pdo->prepare('
        SELECT u.username, u.alias, sa.smart_account_address, sa.is_deployed
        FROM users u
        LEFT JOIN smart_accounts sa ON u.user_id = sa.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $user = ['username' => $_SESSION['username'] ?? 'Usuario'];
}

$smartAccount = $user['smart_account_address'] ?? null;
$alias = $user['alias'] ?? null;
$username = $user['username'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Recibir Fondos | SocialMask</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'brand-bg-primary': '#0D1117',
                        'brand-bg-secondary': '#161B22',
                        'brand-bg-tertiary': '#1C2128',
                        'brand-bg-hover': '#21262D',
                        'brand-border': '#30363D',
                        'brand-accent': '#3B82F6',
                        'brand-accent-hover': '#2563EB',
                        'brand-success': '#28A745',
                        'brand-error': '#DC3545',
                    },
                    fontFamily: {
                        'inter': ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        };
    </script>
    
    <style>
        body { 
            font-family: 'Inter', system-ui, sans-serif;
        }
        #receive-status[data-type="success"] { color: #28a745; }
        #receive-status[data-type="error"] { color: #dc3545; }
        #receive-status[data-type="info"] { color: #3B82F6; }
        
        /* QR Code styling */
        #qr-code-container canvas {
            border-radius: 1rem;
            padding: 1rem;
            background: white;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-white font-inter">

    <!-- Navbar -->
    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8 pt-24 md:pt-32 max-w-6xl">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-4">
                <a href="/wallet" class="text-gray-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h1 class="text-3xl md:text-4xl font-bold">Recibir Fondos</h1>
            </div>
            <p class="text-gray-400 text-sm md:text-base">
                Recibe USDT o SPHE directamente en tu Smart Account de Polygon Network sin comisiones de gas
            </p>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Columna Principal: Dirección de Depósito -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Tarjeta de Dirección -->
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-6 space-y-6">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <h2 class="text-2xl font-semibold mb-2">Tu Dirección de Depósito</h2>
                            <p id="receive-status" data-type="info" class="text-sm text-gray-400">
                                Preparando dirección...
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button id="copy-address-btn" 
                                    class="px-5 py-2.5 rounded-lg bg-brand-accent text-white text-sm font-semibold hover:bg-brand-accent-hover transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                Copiar
                            </button>
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div class="bg-brand-bg-tertiary border border-brand-border rounded-xl p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-wide text-gray-400 font-semibold">
                                Dirección de Depósito
                            </span>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-1 bg-purple-500/10 text-purple-400 rounded text-xs font-semibold">
                                    Polygon
                                </span>
                            </div>
                        </div>
                        <code id="deposit-address" class="block break-all text-base md:text-lg font-mono text-white bg-brand-bg-primary px-4 py-3 rounded-lg border border-brand-border">
                            0x...
                        </code>
                    </div>

                    <!-- QR Code -->
                    <div class="flex justify-center">
                        <div id="qr-code-container" class="bg-white p-4 rounded-xl"></div>
                    </div>

                    <!-- Info de Smart Account -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="bg-brand-bg-tertiary border border-brand-border rounded-xl p-4">
                            <span class="text-xs uppercase tracking-wide text-gray-400 block mb-2 font-semibold">
                                Smart Account
                            </span>
                            <code id="smart-account-address" class="break-all text-sm text-white font-mono">
                                <?php echo $smartAccount ? htmlspecialchars($smartAccount, ENT_QUOTES, 'UTF-8') : 'Pendiente de generar'; ?>
                            </code>
                        </div>
                        <div class="bg-brand-bg-tertiary border border-brand-border rounded-xl p-4">
                            <span class="text-xs uppercase tracking-wide text-gray-400 block mb-2 font-semibold">
                                Estado
                            </span>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                <span id="address-expiry" class="text-sm text-white font-medium">Activa</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instrucciones -->
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-6 space-y-4">
                    <h3 class="text-xl font-semibold flex items-center gap-2">
                        <svg class="w-6 h-6 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        ¿Cómo recibir fondos?
                    </h3>
                    <ol class="space-y-3 text-sm text-gray-400">
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-brand-accent/20 text-brand-accent flex items-center justify-center text-xs font-bold">1</span>
                            <div>
                                <strong class="text-white">Copia la dirección</strong> o escanea el código QR desde tu wallet o exchange
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-brand-accent/20 text-brand-accent flex items-center justify-center text-xs font-bold">2</span>
                            <div>
                                <strong class="text-white">Envía USDT o SPHE</strong> en la red <strong class="text-purple-400">Polygon Network</strong>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full bg-brand-accent/20 text-brand-accent flex items-center justify-center text-xs font-bold">3</span>
                            <div>
                                <strong class="text-white">Espera la confirmación</strong> (generalmente 1-2 minutos). Los fondos aparecerán en tu Dashboard
                            </div>
                        </li>
                    </ol>
                </div>

            </div>

            <!-- Columna Lateral: Información -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Tokens Aceptados -->
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-5">
                    <h3 class="text-lg font-semibold mb-4">Tokens Aceptados</h3>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-brand-bg-tertiary rounded-lg border border-brand-border hover:border-green-500/50 transition-colors">
                            <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center font-bold text-white text-sm">
                                ₮
                            </div>
                            <div>
                                <p class="text-white font-semibold text-sm">USDT</p>
                                <p class="text-xs text-gray-400">Tether USD (Polygon)</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-brand-bg-tertiary rounded-lg border border-brand-border hover:border-brand-accent/50 transition-colors">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-accent to-purple-600 flex items-center justify-center font-bold text-white text-xs">
                                SPH
                            </div>
                            <div>
                                <p class="text-white font-semibold text-sm">SPHE</p>
                                <p class="text-xs text-gray-400">SocialMask Token</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advertencias de Seguridad -->
                <div class="bg-red-500/10 border border-red-500/20 rounded-2xl p-5">
                    <div class="flex items-start gap-3 mb-3">
                        <svg class="w-6 h-6 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <div>
                            <h3 class="text-red-400 font-semibold text-sm mb-1">Importante</h3>
                        </div>
                    </div>
                    <ul class="space-y-2 text-xs text-red-300">
                        <li class="flex gap-2">
                            <span>•</span>
                            <span>Solo envía desde <strong>Polygon Network</strong></span>
                        </li>
                        <li class="flex gap-2">
                            <span>•</span>
                            <span>No envíes desde otras redes (Ethereum, BSC, etc.)</span>
                        </li>
                        <li class="flex gap-2">
                            <span>•</span>
                            <span>Verifica bien la dirección antes de enviar</span>
                        </li>
                        <li class="flex gap-2">
                            <span>•</span>
                            <span>Solo USDT y SPHE son aceptados</span>
                        </li>
                    </ul>
                </div>

                <!-- Ventajas -->
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-5">
                    <h3 class="text-lg font-semibold mb-4">Ventajas</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Sin comisiones de red (gas patrocinado)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Privacidad total con Smart Accounts</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Confirmaciones rápidas (1-2 minutos)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Tus llaves, tu control absoluto</span>
                        </li>
                    </ul>
                </div>

                <!-- FAQ Rápido -->
                <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-5">
                    <h3 class="text-lg font-semibold mb-4">Preguntas Frecuentes</h3>
                    <div class="space-y-3 text-xs text-gray-400">
                        <div>
                            <strong class="text-white">¿Cuánto tarda?</strong>
                            <p class="mt-1">Normalmente 1-2 minutos en Polygon</p>
                        </div>
                        <div>
                            <strong class="text-white">¿Hay límites?</strong>
                            <p class="mt-1">No hay límites mínimos ni máximos</p>
                        </div>
                        <div>
                            <strong class="text-white">¿Es seguro?</strong>
                            <p class="mt-1">Sí, tu Smart Account está protegida con tu passkey y es no-custodial</p>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="/assets/js/wallet-receive.js"></script>
</body>
</html>
