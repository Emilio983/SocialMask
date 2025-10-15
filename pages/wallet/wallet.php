<?php
/**
 * thesocialmask - Wallet Page
 * Página unificada de wallet con balance, acciones gasless y retiros
 */

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../api/utils.php';

// Verificar sesión
if (!isAuthenticated()) {
    header('Location: /pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';

// Obtener datos del usuario
try {
    $stmt = $pdo->prepare("
        SELECT u.*, sa.smart_account_address, sa.deployment_tx_hash
        FROM users u
        LEFT JOIN smart_accounts sa ON u.user_id = sa.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $user = ['username' => $username];
}

$page_title = "Wallet | SocialMask";
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
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
                        'brand-accent': '#3B82F6',
                        'brand-accent-hover': '#2563EB',
                        'brand-border': '#30363D',
                    },
                    fontFamily: {
                        'inter': ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }
    </style>
</head>

<body class="bg-brand-bg-primary text-white font-inter">

    <!-- Navbar -->
    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8 pt-24 md:pt-32 max-w-7xl">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold mb-2 flex items-center gap-3">
                <svg class="w-10 h-10 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <span>Mi Wallet</span>
            </h1>
            <p class="text-gray-400 text-sm md:text-base">
                Gestiona tus fondos SPHE y USDT sin comisiones de gas
            </p>
        </div>

        <!-- Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Columna Izquierda: Balance Widget -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Balance Widget -->
                <div class="bg-brand-bg-secondary rounded-2xl p-5 border border-brand-border">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-400">Tus Balances</h3>
                        <button onclick="window.reloadWalletBalances && window.reloadWalletBalances()" class="text-gray-400 hover:text-white transition" title="Recargar balances">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- SPHE Balance -->
                    <div class="mb-4 pb-4 border-b border-brand-border">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-brand-accent/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <span class="text-sm text-gray-400">SPHE</span>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-white" id="wallet-sphe-balance">
                            <span class="inline-block w-24 h-8 bg-brand-border animate-pulse rounded"></span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Polygon Network</div>
                    </div>

                    <!-- USDT Balance -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <span class="text-sm text-gray-400">USDT (Polygon)</span>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-white" id="wallet-usdt-balance">
                            <span class="inline-block w-24 h-8 bg-brand-border animate-pulse rounded"></span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Tether USD en Polygon</div>
                    </div>
                </div>

                <!-- Info de Smart Account -->
                <?php if ($user && !empty($user['smart_account_address'])): ?>
                <div class="bg-brand-bg-secondary rounded-2xl p-4 border border-brand-border">
                    <h3 class="text-sm font-semibold text-gray-400 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span>Smart Account</span>
                    </h3>
                    <div class="flex items-center gap-2 mb-2">
                        <code class="text-xs text-white bg-brand-bg-primary px-3 py-2 rounded-lg font-mono flex-1 truncate" id="wallet-address-display">
                            <?php echo htmlspecialchars($user['smart_account_address']); ?>
                        </code>
                        <button onclick="copyAddress()" class="text-gray-400 hover:text-white transition p-2" title="Copiar dirección">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="px-2 py-1 bg-purple-500/10 text-purple-400 rounded">Polygon</span>
                        <span class="px-2 py-1 bg-green-500/10 text-green-400 rounded">Gas Patrocinado</span>
                    </div>
                    <?php if (!empty($user['deployment_tx_hash'])): ?>
                        <a href="https://polygonscan.com/tx/<?php echo htmlspecialchars($user['deployment_tx_hash']); ?>" 
                           target="_blank"
                           class="text-xs text-brand-accent hover:underline mt-3 inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            <span>Ver en PolygonScan</span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Enlaces rápidos -->
                <div class="bg-brand-bg-secondary rounded-2xl p-4 border border-brand-border">
                    <h3 class="text-sm font-semibold text-white mb-3">Acciones</h3>
                    <div class="space-y-2">
                        <a href="/receive" class="flex items-center gap-3 p-3 rounded-xl hover:bg-brand-bg-hover transition-colors group">
                            <div class="w-10 h-10 rounded-lg bg-brand-accent/20 flex items-center justify-center group-hover:bg-brand-accent/30 transition">
                                <svg class="w-5 h-5 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-white font-medium text-sm">Recibir</p>
                                <p class="text-xs text-gray-400">Generar dirección</p>
                            </div>
                        </a>
                        <button onclick="switchTab('withdraw')" 
                                class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-brand-bg-hover transition-colors group">
                            <div class="w-10 h-10 rounded-lg bg-brand-accent/20 flex items-center justify-center group-hover:bg-brand-accent/30 transition">
                                <svg class="w-5 h-5 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="text-white font-medium text-sm">Enviar</p>
                                <p class="text-xs text-gray-400">A otra wallet</p>
                            </div>
                        </button>
                        <a href="/dashboard" class="flex items-center gap-3 p-3 rounded-xl hover:bg-brand-bg-hover transition-colors group">
                            <div class="w-10 h-10 rounded-lg bg-brand-accent/20 flex items-center justify-center group-hover:bg-brand-accent/30 transition">
                                <svg class="w-5 h-5 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-white font-medium text-sm">Dashboard</p>
                                <p class="text-xs text-gray-400">Panel principal</p>
                            </div>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Columna Derecha: Enviar/Historial -->
            <div class="lg:col-span-2">
                
                <!-- Tabs -->
                <div class="bg-brand-bg-secondary rounded-2xl border border-brand-border overflow-hidden">
                    
                    <!-- Tab Headers -->
                    <div class="flex border-b border-brand-border">
                        <button onclick="switchTab('withdraw')" id="tab-withdraw" class="tab-button active flex-1 px-6 py-4 text-sm font-semibold transition-colors">
                            Enviar Fondos
                        </button>
                        <button onclick="switchTab('history')" id="tab-history" class="tab-button flex-1 px-6 py-4 text-sm font-semibold transition-colors">
                            Historial
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">

                        
                        <!-- Tab: Enviar Fondos -->
                        <div id="content-withdraw" class="tab-content">
                            <h2 class="text-2xl font-bold mb-2">Enviar Fondos</h2>
                            <p class="text-gray-400 mb-6">Envía SPHE o USDT a cualquier dirección en Polygon Network</p>
                            
                            <!-- Formulario de envío -->
                            <div class="bg-brand-bg-tertiary rounded-xl p-6 border border-brand-border">
                                <form id="withdrawForm" class="space-y-5">
                                    <!-- Token selector -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2 text-gray-300">Token a enviar</label>
                                        <select id="withdrawToken" class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-white focus:border-brand-accent focus:outline-none transition">
                                            <option value="SPHE">SPHE (SocialMask Token)</option>
                                            <option value="USDT">USDT (Tether USD - Polygon)</option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Ambos tokens están en Polygon Network
                                        </p>
                                    </div>

                                    <!-- Dirección destino -->
                                    <div>
                                        <label class="block text-sm font-medium mb-2 text-gray-300">Dirección de destino</label>
                                        <input 
                                            type="text" 
                                            id="withdrawAddress"
                                            placeholder="0x..."
                                            class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-brand-accent focus:outline-none transition font-mono text-sm"
                                            required
                                        />
                                        <p class="text-xs text-gray-500 mt-1">
                                            Dirección válida de Polygon Network
                                        </p>
                                    </div>

                                    <!-- Cantidad -->
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="block text-sm font-medium text-gray-300">Cantidad</label>
                                            <button type="button" onclick="setMaxWithdraw()" class="text-xs text-brand-accent hover:underline">
                                                Enviar todo
                                            </button>
                                        </div>
                                        <div class="relative">
                                            <input 
                                                type="number" 
                                                id="withdrawAmount"
                                                placeholder="0.00"
                                                step="0.01"
                                                min="0"
                                                class="w-full bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-brand-accent focus:outline-none transition"
                                                required
                                            />
                                        </div>
                                        <div class="flex items-center justify-between mt-2">
                                            <p class="text-xs text-gray-500">
                                                Disponible: <span id="withdrawAvailable" class="text-white font-semibold">0.00</span>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Botón de envío -->
                                    <button 
                                        type="submit"
                                        class="w-full bg-brand-accent hover:bg-brand-accent-hover text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                        Enviar Fondos
                                    </button>
                                </form>

                                <!-- Nota sobre la red -->
                                <div class="mt-5 space-y-2">
                                    <div class="p-3 bg-purple-500/10 border border-purple-500/20 rounded-lg">
                                        <div class="flex items-start gap-2">
                                            <svg class="w-5 h-5 text-purple-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-xs text-purple-400 font-semibold">Red Polygon</p>
                                                <p class="text-xs text-purple-300 mt-0.5">
                                                    Solo puedes enviar a direcciones de Polygon Network. Si envías a otra red, perderás tus fondos.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 bg-green-500/10 border border-green-500/20 rounded-lg">
                                        <div class="flex items-start gap-2">
                                            <svg class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <div>
                                                <p class="text-xs text-green-400 font-semibold">Gas Patrocinado</p>
                                                <p class="text-xs text-green-300 mt-0.5">
                                                    No pagas comisiones. SocialMask cubre el gas de todas tus transacciones.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Historial -->
                        <div id="content-history" class="tab-content hidden">
                            <h2 class="text-2xl font-bold mb-2">Historial de Transacciones</h2>
                            <p class="text-gray-400 mb-6">Todas tus transacciones en Polygon Network</p>
                            
                            <!-- Filtros -->
                            <div class="flex flex-wrap items-center gap-3 mb-6">
                                <select class="bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-2 text-white text-sm focus:border-brand-accent focus:outline-none">
                                    <option>Todas las transacciones</option>
                                    <option>Solo enviadas</option>
                                    <option>Solo recibidas</option>
                                </select>
                                <select class="bg-brand-bg-primary border border-brand-border rounded-lg px-4 py-2 text-white text-sm focus:border-brand-accent focus:outline-none">
                                    <option>Últimos 7 días</option>
                                    <option>Últimos 30 días</option>
                                    <option>Últimos 3 meses</option>
                                    <option>Todo el tiempo</option>
                                </select>
                                <button class="ml-auto text-brand-accent hover:underline text-sm flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    Exportar
                                </button>
                            </div>

                            <!-- Lista de transacciones (placeholder) -->
                            <div class="text-center py-16 text-gray-400">
                                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                <p class="text-lg font-medium">No hay transacciones aún</p>
                                <p class="text-sm mt-2">Cuando envíes o recibas fondos, aparecerán aquí</p>
                                <a href="/receive" class="inline-flex items-center gap-2 mt-4 text-brand-accent hover:underline">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Recibir fondos para empezar
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </main>

    <!-- Scripts -->
    <script>
        // ============================================
        // DATOS DE SESIÓN
        // ============================================
        const SESSION_DATA = {
            user_id: '<?php echo $user_id; ?>',
            username: '<?php echo htmlspecialchars($username); ?>',
            smart_account_address: '<?php echo !empty($user['smart_account_address']) ? htmlspecialchars($user['smart_account_address']) : ''; ?>'
        };

        // ============================================
        // SISTEMA DE TABS
        // ============================================
        function switchTab(tabName) {
            // Ocultar todos los contenidos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remover clase active de todos los botones
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'text-brand-accent', 'bg-brand-bg-primary');
                button.classList.add('text-gray-400', 'hover:text-white');
            });

            // Mostrar contenido seleccionado
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Activar botón seleccionado
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.add('active', 'text-brand-accent', 'bg-brand-bg-primary');
            activeButton.classList.remove('text-gray-400', 'hover:text-white');
        }

        // Inicializar tabs con estilos
        document.querySelectorAll('.tab-button').forEach(button => {
            if (!button.classList.contains('active')) {
                button.classList.add('text-gray-400', 'hover:text-white');
            }
        });

        // ============================================
        // COPIAR DIRECCIÓN
        // ============================================
        function copyAddress() {
            const address = SESSION_DATA.smart_account_address;
            if (!address) return;

            navigator.clipboard.writeText(address).then(() => {
                showToastMessage('✅ Dirección copiada al portapapeles', 'success');
            }).catch(err => {
                console.error('Error al copiar:', err);
                showToastMessage('❌ Error al copiar dirección', 'error');
            });
        }

        // ============================================
        // CARGAR BALANCES
        // ============================================
        let balancesState = {
            sphe: 0,
            usdt: 0,
            loading: false
        };

        async function loadWalletBalances() {
            if (balancesState.loading) return;
            
            balancesState.loading = true;
            console.log('🔄 Cargando balances del wallet...');

            try {
                const response = await fetch('/api/wallet/balances.php', {
                    method: 'GET',
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();
                console.log('📊 Balances recibidos:', data);

                if (data.success && data.balances) {
                    // Guardar balances
                    balancesState.sphe = parseFloat(data.balances.sphe.formatted) || 0;
                    balancesState.usdt = parseFloat(data.balances.usdt.formatted) || 0;

                    // Actualizar UI
                    updateBalanceDisplay();
                    console.log('✅ Balances actualizados');
                } else {
                    throw new Error(data.message || 'Error desconocido');
                }
            } catch (error) {
                console.error('❌ Error cargando balances:', error);
                showErrorBalance();
            } finally {
                balancesState.loading = false;
            }
        }

        function updateBalanceDisplay() {
            const fmtToken = new Intl.NumberFormat('es-MX', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 6
            });

            // Actualizar SPHE
            const spheEl = document.getElementById('wallet-sphe-balance');
            if (spheEl) {
                spheEl.innerHTML = `${fmtToken.format(balancesState.sphe)} SPHE`;
            }

            const spheUsdEl = document.getElementById('wallet-sphe-usd');
            if (spheUsdEl) {
                // Placeholder para precio SPHE
                spheUsdEl.textContent = '~$0.00 USD';
            }

            // Actualizar USDT
            const usdtEl = document.getElementById('wallet-usdt-balance');
            if (usdtEl) {
                usdtEl.innerHTML = `${fmtToken.format(balancesState.usdt)} USDT`;
            }

            // Actualizar disponible en retiros
            updateWithdrawAvailable();
        }

        function showErrorBalance() {
            const spheEl = document.getElementById('wallet-sphe-balance');
            const usdtEl = document.getElementById('wallet-usdt-balance');
            
            if (spheEl) spheEl.innerHTML = '<span class="text-red-400 text-sm">Error</span>';
            if (usdtEl) usdtEl.innerHTML = '<span class="text-red-400 text-sm">Error</span>';
        }

        // ============================================
        // SISTEMA DE RETIROS
        // ============================================
        function updateWithdrawAvailable() {
            const tokenSelect = document.getElementById('withdrawToken');
            const availableEl = document.getElementById('withdrawAvailable');
            
            if (!tokenSelect || !availableEl) return;

            const token = tokenSelect.value;
            const balance = token === 'SPHE' ? balancesState.sphe : balancesState.usdt;
            
            availableEl.textContent = `${balance.toFixed(2)} ${token}`;
        }

        function setMaxWithdraw() {
            const tokenSelect = document.getElementById('withdrawToken');
            const amountInput = document.getElementById('withdrawAmount');
            
            if (!tokenSelect || !amountInput) return;

            const token = tokenSelect.value;
            const balance = token === 'SPHE' ? balancesState.sphe : balancesState.usdt;
            
            amountInput.value = balance.toFixed(2);
        }

        // Event listeners para retiros
        const withdrawForm = document.getElementById('withdrawForm');
        if (withdrawForm) {
            withdrawForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const token = document.getElementById('withdrawToken').value;
                const address = document.getElementById('withdrawAddress').value;
                const amount = parseFloat(document.getElementById('withdrawAmount').value);

                if (!address || !amount || amount <= 0) {
                    showToastMessage('❌ Por favor completa todos los campos', 'error');
                    return;
                }

                const maxBalance = token === 'SPHE' ? balancesState.sphe : balancesState.usdt;
                if (amount > maxBalance) {
                    showToastMessage('❌ Saldo insuficiente', 'error');
                    return;
                }

                showToastMessage('🔄 Procesando retiro... (funcionalidad próximamente)', 'info');
                console.log('Retiro solicitado:', { token, address, amount });
            });
        }

        const withdrawTokenSelect = document.getElementById('withdrawToken');
        if (withdrawTokenSelect) {
            withdrawTokenSelect.addEventListener('change', updateWithdrawAvailable);
        }

        // ============================================
        // FUNCIÓN DE TOAST
        // ============================================
        function showToastMessage(message, type = 'info') {
            if (typeof showToast === 'function') {
                showToast(message, type);
            } else {
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        }

        // ============================================
        // ACCIONES GASLESS (PLACEHOLDER)
        // ============================================
        window.GaslessActions = {
            openModal: function(actionType) {
                showToastMessage(`Funcionalidad de ${actionType} próximamente`, 'info');
                console.log('Acción gasless:', actionType);
            }
        };

        // ============================================
        // INICIALIZACIÓN
        // ============================================
        window.reloadWalletBalances = loadWalletBalances;
        
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🚀 Iniciando wallet...');
            loadWalletBalances();
            
            // Auto-refresh cada 30 segundos
            setInterval(() => {
                if (document.visibilityState === 'visible') {
                    loadWalletBalances();
                }
            }, 30000);
        });
    </script>

    <!-- Sistema de envío -->
    <script src="/assets/js/wallet-send.js"></script>

</body>
</html>
