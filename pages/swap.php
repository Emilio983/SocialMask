<?php
/**
 * SWAP PAGE - Token Exchange with 0x Protocol
 * Permite intercambiar USDT (Polygon) por SPHE usando 0x API
 */
require_once __DIR__ . '/../config/connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Get user wallet
$stmt = $pdo->prepare("SELECT wallet_address FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$userWallet = $user['wallet_address'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swap - The Social Mask</title>
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
        }

        .swap-card {
            transition: all 0.3s ease;
        }

        .swap-card:hover {
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
        }

        .token-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .swap-arrow {
            transition: transform 0.3s ease;
        }

        .swap-arrow:hover {
            transform: rotate(180deg);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .spinner {
            border: 2px solid rgba(59, 130, 246, 0.2);
            border-top-color: #3B82F6;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 0.6s linear infinite;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }

        .alert.show {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86EFAC;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #93C5FD;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">
    <!-- Navigation -->
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <!-- Main Content -->
    <main class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-12">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold text-brand-text-primary mb-2">
                <i class="fas fa-exchange-alt text-brand-accent mr-3"></i>
                Token Swap
            </h1>
            <p class="text-brand-text-secondary text-base sm:text-lg">
                Intercambia USDT por SPHE usando 0x Protocol
            </p>
        </div>

        <!-- Alert -->
        <div id="alert" class="alert"></div>

        <!-- Wallet Connection Notice -->
        <?php if (!$userWallet): ?>
        <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-6 mb-6 text-center">
            <i class="fas fa-wallet text-4xl text-brand-accent mb-3"></i>
            <h3 class="text-xl font-semibold mb-2">Wallet No Conectada</h3>
            <p class="text-brand-text-secondary mb-4">Conecta tu wallet para poder realizar swaps</p>
            <a href="/wallet" class="inline-block bg-brand-accent hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold transition">
                <i class="fas fa-link mr-2"></i>Conectar Wallet
            </a>
        </div>
        <?php else: ?>

        <!-- Swap Container -->
        <div class="bg-brand-bg-secondary border border-brand-border rounded-xl p-6 shadow-lg">
            <!-- Settings -->
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Swap</h3>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-brand-text-secondary">Slippage:</span>
                    <select id="slippage" class="bg-brand-bg-primary border border-brand-border text-brand-text-primary rounded-lg px-3 py-1 text-sm focus:ring-2 focus:ring-brand-accent">
                        <option value="0.5">0.5%</option>
                        <option value="1" selected>1%</option>
                        <option value="3">3%</option>
                        <option value="5">5%</option>
                    </select>
                </div>
            </div>

            <!-- From Token -->
            <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-4 mb-2 swap-card">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-sm text-brand-text-secondary font-medium">Enviar</label>
                    <span class="text-sm text-brand-text-secondary">
                        Balance: <span id="from-balance">0</span> <span id="from-symbol">USDT</span>
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <input 
                        type="number" 
                        id="from-amount" 
                        placeholder="0.0" 
                        class="flex-1 bg-transparent text-2xl font-semibold text-brand-text-primary outline-none" 
                        step="0.000001"
                        min="0"
                    >
                    <button id="max-btn" class="text-xs bg-brand-accent hover:bg-blue-600 text-white px-3 py-1 rounded-md font-semibold transition">
                        MAX
                    </button>
                    <div id="from-token-card" class="flex items-center gap-2 bg-brand-bg-secondary border border-brand-border rounded-lg px-3 py-2">
                        <div class="token-icon">U</div>
                        <span class="token-name font-semibold">USDT</span>
                    </div>
                </div>
            </div>

            <!-- Swap Arrow -->
            <div class="flex justify-center -my-2 relative z-10">
                <button id="swap-direction" class="bg-brand-bg-secondary border-4 border-brand-bg-primary rounded-full p-2 hover:bg-brand-bg-primary transition swap-arrow">
                    <i class="fas fa-arrow-down text-brand-accent text-xl"></i>
                </button>
            </div>

            <!-- To Token -->
            <div class="bg-brand-bg-primary border border-brand-border rounded-lg p-4 mb-4 swap-card">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-sm text-brand-text-secondary font-medium">Recibir (estimado)</label>
                    <span class="text-sm text-brand-text-secondary">
                        Balance: <span id="to-balance">0</span> <span id="to-symbol">SPHE</span>
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <input 
                        type="text" 
                        id="to-amount" 
                        placeholder="0.0" 
                        class="flex-1 bg-transparent text-2xl font-semibold text-brand-text-primary outline-none" 
                        readonly
                    >
                    <div id="to-token-card" class="flex items-center gap-2 bg-brand-bg-secondary border border-brand-border rounded-lg px-3 py-2">
                        <div class="token-icon">S</div>
                        <span class="token-name font-semibold">SPHE</span>
                    </div>
                </div>
            </div>

            <!-- Swap Details -->
            <div id="swap-details" class="bg-brand-bg-primary border border-brand-border rounded-lg p-4 mb-4 hidden">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-brand-text-secondary">Tasa de cambio</span>
                        <span class="font-medium" id="exchange-rate">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-brand-text-secondary">Impacto de precio</span>
                        <span class="font-medium" id="price-impact">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-brand-text-secondary">Mínimo recibido</span>
                        <span class="font-medium" id="min-received">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-brand-text-secondary">Fee de gas</span>
                        <span class="font-medium text-green-400" id="gas-fee">Patrocinado ✨</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-brand-text-secondary">Proveedor de liquidez</span>
                        <span class="font-medium">0x Protocol</span>
                    </div>
                </div>
            </div>

            <!-- Swap Button -->
            <button id="swap-btn" class="w-full bg-brand-accent hover:bg-blue-600 text-white py-4 rounded-lg font-semibold text-lg transition shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Ingresa un monto
            </button>

            <!-- Network Info -->
            <div class="mt-4 text-center text-xs text-brand-text-secondary">
                <i class="fas fa-network-wired mr-1"></i>
                Red: Polygon · Patrocinado por Biconomy
            </div>
        </div>

        <!-- Info Section -->
        <div class="mt-8 bg-brand-bg-secondary border border-brand-border rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-info-circle text-brand-accent mr-2"></i>
                ¿Cómo funciona?
            </h3>
            <ul class="space-y-3 text-sm text-brand-text-secondary">
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-400 mr-2 mt-0.5"></i>
                    <span><strong class="text-brand-text-primary">0x Protocol:</strong> Agregador de liquidez que encuentra el mejor precio entre múltiples DEXs</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-400 mr-2 mt-0.5"></i>
                    <span><strong class="text-brand-text-primary">Sin Gas:</strong> Las transacciones son patrocinadas por Biconomy Gasless</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-400 mr-2 mt-0.5"></i>
                    <span><strong class="text-brand-text-primary">Seguro:</strong> Tus fondos nunca salen de tu wallet hasta confirmar</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-400 mr-2 mt-0.5"></i>
                    <span><strong class="text-brand-text-primary">Mejor precio:</strong> Compara automáticamente entre Uniswap, Sushiswap, QuickSwap y más</span>
                </li>
            </ul>
        </div>

        <?php endif; ?>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.ethers.io/lib/ethers-5.7.2.umd.min.js"></script>
    <script>
        const userWallet = '<?php echo $userWallet ?? ''; ?>';
        let balances = { usdt: 0, sphe: 0 };
        let currentQuote = null;
        let swapDirection = 'usdt-to-sphe';

        // Contract addresses (Polygon mainnet)
        const TOKENS = {
            USDT: '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', // USDT on Polygon
            SPHE: '0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b' // SPHE Token actual address
        };

        // Show alert
        function showAlert(message, type = 'info') {
            const alert = document.getElementById('alert');
            alert.className = `alert alert-${type} show`;
            alert.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i><span>${message}</span>`;
            setTimeout(() => alert.classList.remove('show'), 5000);
        }

        // Load balances
        async function loadBalances() {
            if (!userWallet) return;

            try {
                const response = await fetch('/api/wallet/balances.php');
                const data = await response.json();
                
                if (data.success && data.balances) {
                    balances.usdt = parseFloat(data.balances.usdt?.formatted || 0);
                    balances.sphe = parseFloat(data.balances.sphe?.formatted || 0);
                    
                    document.getElementById('from-balance').textContent = balances.usdt.toFixed(6);
                    document.getElementById('to-balance').textContent = balances.sphe.toFixed(6);
                }
            } catch (error) {
                console.error('Error loading balances:', error);
                showAlert('Error al cargar balances', 'error');
            }
        }

        // Get swap quote from 0x API
        let quoteTimeout;
        async function getQuote() {
            const amount = document.getElementById('from-amount').value;
            
            if (!amount || parseFloat(amount) <= 0) {
                document.getElementById('swap-details').classList.add('hidden');
                document.getElementById('swap-btn').disabled = true;
                document.getElementById('swap-btn').textContent = 'Ingresa un monto';
                document.getElementById('to-amount').value = '';
                return;
            }

            const slippage = parseFloat(document.getElementById('slippage').value);
            const fromToken = swapDirection === 'usdt-to-sphe' ? 'USDT' : 'SPHE';
            const toToken = swapDirection === 'usdt-to-sphe' ? 'SPHE' : 'USDT';

            document.getElementById('swap-btn').disabled = true;
            document.getElementById('swap-btn').innerHTML = '<div class="spinner inline-block mr-2"></div> Obteniendo cotización...';

            try {
                const response = await fetch('/api/wallet/swap_quote.php?' + new URLSearchParams({
                    fromToken,
                    toToken,
                    amount,
                    slippage,
                    userAddress: userWallet
                }));

                const data = await response.json();

                if (data.success && data.quote) {
                    currentQuote = data.quote;
                    
                    // Update UI
                    const buyAmount = parseFloat(data.quote.buyAmount) / 1e18; // Assuming 18 decimals
                    document.getElementById('to-amount').value = buyAmount.toFixed(6);
                    document.getElementById('exchange-rate').textContent = 
                        `1 ${fromToken} = ${data.quote.price} ${toToken}`;
                    document.getElementById('price-impact').textContent = 
                        data.quote.estimatedPriceImpact ? `${data.quote.estimatedPriceImpact}%` : '< 0.01%';
                    
                    const minAmount = buyAmount * (1 - slippage / 100);
                    document.getElementById('min-received').textContent = 
                        `${minAmount.toFixed(6)} ${toToken}`;
                    
                    document.getElementById('swap-details').classList.remove('hidden');
                    document.getElementById('swap-btn').disabled = false;
                    document.getElementById('swap-btn').textContent = '🔄 Realizar Swap';
                } else {
                    throw new Error(data.message || 'No se pudo obtener cotización');
                }
            } catch (error) {
                console.error('Error getting quote:', error);
                showAlert('Error al obtener cotización: ' + error.message, 'error');
                document.getElementById('swap-btn').textContent = 'Error al cotizar';
                document.getElementById('to-amount').value = '';
            }
        }

        // Execute swap
        async function executeSwap() {
            if (!currentQuote) {
                showAlert('No hay cotización disponible', 'error');
                return;
            }

            const amount = document.getElementById('from-amount').value;
            const fromToken = swapDirection === 'usdt-to-sphe' ? 'USDT' : 'SPHE';
            
            // Check balance
            const currentBalance = swapDirection === 'usdt-to-sphe' ? balances.usdt : balances.sphe;
            if (parseFloat(amount) > currentBalance) {
                showAlert(`Balance insuficiente. Tienes ${currentBalance.toFixed(6)} ${fromToken}`, 'error');
                return;
            }

            document.getElementById('swap-btn').disabled = true;
            document.getElementById('swap-btn').innerHTML = '<div class="spinner inline-block mr-2"></div> Procesando swap...';

            try {
                const response = await fetch('/api/wallet/execute_swap.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        fromToken,
                        toToken: swapDirection === 'usdt-to-sphe' ? 'SPHE' : 'USDT',
                        amount,
                        quote: currentQuote,
                        userAddress: userWallet,
                        slippage: parseFloat(document.getElementById('slippage').value)
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('✅ Swap completado exitosamente!', 'success');
                    document.getElementById('from-amount').value = '';
                    document.getElementById('to-amount').value = '';
                    document.getElementById('swap-details').classList.add('hidden');
                    currentQuote = null;
                    
                    // Reload balances after 3 seconds
                    setTimeout(loadBalances, 3000);
                } else {
                    throw new Error(data.message || 'Error al ejecutar swap');
                }
            } catch (error) {
                console.error('Error executing swap:', error);
                showAlert('Error al ejecutar swap: ' + error.message, 'error');
            } finally {
                document.getElementById('swap-btn').disabled = false;
                document.getElementById('swap-btn').textContent = '🔄 Realizar Swap';
            }
        }

        // Event listeners
        document.getElementById('from-amount')?.addEventListener('input', () => {
            clearTimeout(quoteTimeout);
            quoteTimeout = setTimeout(getQuote, 800);
        });

        document.getElementById('slippage')?.addEventListener('change', getQuote);

        document.getElementById('max-btn')?.addEventListener('click', () => {
            const maxBalance = swapDirection === 'usdt-to-sphe' ? balances.usdt : balances.sphe;
            document.getElementById('from-amount').value = maxBalance;
            getQuote();
        });

        document.getElementById('swap-direction')?.addEventListener('click', () => {
            swapDirection = swapDirection === 'usdt-to-sphe' ? 'sphe-to-usdt' : 'usdt-to-sphe';
            
            // Swap values
            const fromVal = document.getElementById('from-amount').value;
            const toVal = document.getElementById('to-amount').value;
            document.getElementById('from-amount').value = toVal;
            document.getElementById('to-amount').value = fromVal;
            
            // Swap balances display
            const fromBal = document.getElementById('from-balance').textContent;
            const toBal = document.getElementById('to-balance').textContent;
            document.getElementById('from-balance').textContent = toBal;
            document.getElementById('to-balance').textContent = fromBal;
            
            // Swap symbols
            const fromSym = document.getElementById('from-symbol').textContent;
            const toSym = document.getElementById('to-symbol').textContent;
            document.getElementById('from-symbol').textContent = toSym;
            document.getElementById('to-symbol').textContent = fromSym;
            
            // Swap token displays (logos and names in the cards)
            const fromTokenCard = document.querySelector('#from-token-card');
            const toTokenCard = document.querySelector('#to-token-card');
            
            if (fromTokenCard && toTokenCard) {
                // Get current token info
                const fromIcon = fromTokenCard.querySelector('.token-icon');
                const fromName = fromTokenCard.querySelector('.token-name');
                const toIcon = toTokenCard.querySelector('.token-icon');
                const toName = toTokenCard.querySelector('.token-name');
                
                // Swap icons
                const fromIconText = fromIcon.textContent;
                const toIconText = toIcon.textContent;
                fromIcon.textContent = toIconText;
                toIcon.textContent = fromIconText;
                
                // Swap names
                const fromNameText = fromName.textContent;
                const toNameText = toName.textContent;
                fromName.textContent = toNameText;
                toName.textContent = fromNameText;
            }
            
            if (fromVal) getQuote();
        });

        document.getElementById('swap-btn')?.addEventListener('click', executeSwap);

        // Initialize
        if (userWallet) {
            loadBalances();
        }
    </script>
</body>
</html>
