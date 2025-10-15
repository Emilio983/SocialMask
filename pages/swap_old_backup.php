<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swap - SocialMask</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 80px 20px 40px;
        }

        .swap-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .swap-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .swap-header h1 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .swap-header p {
            color: #666;
            font-size: 14px;
        }

        .swap-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            border: 2px solid #e9ecef;
        }

        .swap-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .swap-card-header label {
            font-size: 14px;
            font-weight: 600;
            color: #666;
        }

        .balance-label {
            font-size: 13px;
            color: #888;
        }

        .token-input-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .token-input {
            flex: 1;
            font-size: 24px;
            font-weight: 600;
            border: none;
            background: transparent;
            color: #1a1a1a;
            outline: none;
        }

        .token-input::placeholder {
            color: #ccc;
        }

        .token-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: white;
            border-radius: 12px;
            font-weight: 600;
            color: #1a1a1a;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid #e9ecef;
        }

        .token-selector:hover {
            border-color: #667eea;
        }

        .token-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }

        .swap-arrow-container {
            display: flex;
            justify-content: center;
            margin: -8px 0;
            position: relative;
            z-index: 1;
        }

        .swap-arrow-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: white;
            border: 4px solid #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 20px;
        }

        .swap-arrow-btn:hover {
            transform: rotate(180deg);
            border-color: #667eea;
        }

        .swap-info {
            background: #f0f2f5;
            border-radius: 12px;
            padding: 16px;
            margin: 20px 0;
            display: none;
        }

        .swap-info.show {
            display: block;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            color: #666;
        }

        .info-value {
            color: #1a1a1a;
            font-weight: 600;
        }

        .swap-btn {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 20px;
        }

        .swap-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .swap-btn.primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .swap-btn:disabled {
            background: #e9ecef;
            color: #aaa;
            cursor: not-allowed;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .max-btn {
            padding: 4px 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .max-btn:hover {
            background: #5568d3;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert.error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert.success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }

        .slippage-settings {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .slippage-label {
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }

        .slippage-options {
            display: flex;
            gap: 8px;
        }

        .slippage-btn {
            padding: 6px 12px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .slippage-btn.active {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }

        @media (max-width: 640px) {
            .swap-container {
                padding: 24px;
            }

            .token-input {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login');
        exit;
    }
    
    include __DIR__ . '/../components/navbar.php';
    ?>

    <div class="swap-container">
        <div class="swap-header">
            <h1>🔄 Swap Tokens</h1>
            <p>Intercambia tus tokens de manera rápida y segura</p>
        </div>

        <div id="alert" class="alert"></div>

        <div class="slippage-settings">
            <span class="slippage-label">Slippage</span>
            <div class="slippage-options">
                <button class="slippage-btn" data-slippage="0.5">0.5%</button>
                <button class="slippage-btn active" data-slippage="1">1%</button>
                <button class="slippage-btn" data-slippage="3">3%</button>
            </div>
        </div>

        <!-- From Token -->
        <div class="swap-card">
            <div class="swap-card-header">
                <label>Enviar</label>
                <span class="balance-label">Balance: <span id="from-balance">0</span> <span id="from-token-symbol">USDT</span></span>
            </div>
            <div class="token-input-wrapper">
                <input 
                    type="number" 
                    class="token-input" 
                    id="from-amount" 
                    placeholder="0.0"
                    step="0.000001"
                    min="0"
                >
                <button class="max-btn" id="max-btn">MAX</button>
                <div class="token-selector" id="from-token">
                    <div class="token-icon">U</div>
                    <span>USDT</span>
                </div>
            </div>
        </div>

        <!-- Swap Arrow -->
        <div class="swap-arrow-container">
            <button class="swap-arrow-btn" id="swap-direction">
                ↓
            </button>
        </div>

        <!-- To Token -->
        <div class="swap-card">
            <div class="swap-card-header">
                <label>Recibir (estimado)</label>
                <span class="balance-label">Balance: <span id="to-balance">0</span> <span id="to-token-symbol">SPHE</span></span>
            </div>
            <div class="token-input-wrapper">
                <input 
                    type="number" 
                    class="token-input" 
                    id="to-amount" 
                    placeholder="0.0"
                    readonly
                >
                <div class="token-selector" id="to-token">
                    <div class="token-icon">S</div>
                    <span>SPHE</span>
                </div>
            </div>
        </div>

        <!-- Swap Details -->
        <div class="swap-info" id="swap-info">
            <div class="info-row">
                <span class="info-label">Tasa de cambio</span>
                <span class="info-value" id="exchange-rate">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Impacto de precio</span>
                <span class="info-value" id="price-impact">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Mínimo recibido</span>
                <span class="info-value" id="min-received">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fee de gas</span>
                <span class="info-value" id="gas-fee">Patrocinado ✨</span>
            </div>
        </div>

        <button class="swap-btn primary" id="swap-btn" disabled>
            Ingresa un monto
        </button>
    </div>

    <script>
        let balances = { usdt: 0, sphe: 0 };
        let currentQuote = null;
        let slippage = 1;
        let swapDirection = 'usdt-to-sphe'; // or 'sphe-to-usdt'

        // Load balances
        async function loadBalances() {
            try {
                const response = await fetch('/api/wallet/balances.php');
                const data = await response.json();
                
                if (data.success) {
                    balances.usdt = parseFloat(data.balances.usdt?.formatted || 0);
                    balances.sphe = parseFloat(data.balances.sphe?.formatted || 0);
                    
                    document.getElementById('from-balance').textContent = balances.usdt.toFixed(6);
                    document.getElementById('to-balance').textContent = balances.sphe.toFixed(6);
                }
            } catch (error) {
                console.error('Error loading balances:', error);
            }
        }

        // Get swap quote
        let quoteTimeout;
        async function getQuote() {
            const amount = document.getElementById('from-amount').value;
            
            if (!amount || parseFloat(amount) <= 0) {
                document.getElementById('swap-info').classList.remove('show');
                document.getElementById('swap-btn').disabled = true;
                document.getElementById('swap-btn').textContent = 'Ingresa un monto';
                document.getElementById('to-amount').value = '';
                return;
            }

            document.getElementById('swap-btn').disabled = true;
            document.getElementById('swap-btn').innerHTML = '<span class="loading"></span> Obteniendo cotización...';

            try {
                const response = await fetch('/api/wallet/swap_quote.php?' + new URLSearchParams({
                    fromToken: swapDirection === 'usdt-to-sphe' ? 'USDT' : 'SPHE',
                    toToken: swapDirection === 'usdt-to-sphe' ? 'SPHE' : 'USDT',
                    amount: amount,
                    slippage: slippage
                }));

                const data = await response.json();

                if (data.success && data.quote) {
                    currentQuote = data.quote;
                    
                    // Update UI
                    document.getElementById('to-amount').value = parseFloat(data.quote.buyAmount).toFixed(6);
                    document.getElementById('exchange-rate').textContent = 
                        `1 ${swapDirection === 'usdt-to-sphe' ? 'USDT' : 'SPHE'} = ${data.quote.price} ${swapDirection === 'usdt-to-sphe' ? 'SPHE' : 'USDT'}`;
                    document.getElementById('price-impact').textContent = 
                        data.quote.priceImpact ? `${data.quote.priceImpact}%` : '< 0.01%';
                    document.getElementById('min-received').textContent = 
                        `${data.quote.guaranteedAmount} ${swapDirection === 'usdt-to-sphe' ? 'SPHE' : 'USDT'}`;
                    
                    document.getElementById('swap-info').classList.add('show');
                    document.getElementById('swap-btn').disabled = false;
                    document.getElementById('swap-btn').textContent = 'Realizar Swap';
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
            const amount = document.getElementById('from-amount').value;
            
            if (!amount || !currentQuote) {
                showAlert('Datos inválidos', 'error');
                return;
            }

            document.getElementById('swap-btn').disabled = true;
            document.getElementById('swap-btn').innerHTML = '<span class="loading"></span> Procesando swap...';

            try {
                const response = await fetch('/api/wallet/swap_execute.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        fromToken: swapDirection === 'usdt-to-sphe' ? 'USDT' : 'SPHE',
                        toToken: swapDirection === 'usdt-to-sphe' ? 'SPHE' : 'USDT',
                        amount: amount,
                        slippage: slippage,
                        quote: currentQuote
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('✅ Swap realizado exitosamente!', 'success');
                    document.getElementById('from-amount').value = '';
                    document.getElementById('to-amount').value = '';
                    document.getElementById('swap-info').classList.remove('show');
                    currentQuote = null;
                    
                    // Reload balances
                    await loadBalances();
                    
                    document.getElementById('swap-btn').textContent = 'Ingresa un monto';
                } else {
                    throw new Error(data.message || 'Error al ejecutar swap');
                }
            } catch (error) {
                console.error('Error executing swap:', error);
                showAlert('❌ Error: ' + error.message, 'error');
                document.getElementById('swap-btn').textContent = 'Realizar Swap';
                document.getElementById('swap-btn').disabled = false;
            }
        }

        // Show alert
        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert ${type} show`;
            
            setTimeout(() => {
                alert.classList.remove('show');
            }, 5000);
        }

        // Event listeners
        document.getElementById('from-amount').addEventListener('input', () => {
            clearTimeout(quoteTimeout);
            quoteTimeout = setTimeout(getQuote, 800);
        });

        document.getElementById('max-btn').addEventListener('click', () => {
            const maxBalance = swapDirection === 'usdt-to-sphe' ? balances.usdt : balances.sphe;
            document.getElementById('from-amount').value = maxBalance.toFixed(6);
            getQuote();
        });

        document.getElementById('swap-btn').addEventListener('click', executeSwap);

        document.querySelectorAll('.slippage-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.slippage-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                slippage = parseFloat(e.target.dataset.slippage);
                
                // Re-get quote with new slippage
                if (document.getElementById('from-amount').value) {
                    getQuote();
                }
            });
        });

        document.getElementById('swap-direction').addEventListener('click', () => {
            // Swap direction
            swapDirection = swapDirection === 'usdt-to-sphe' ? 'sphe-to-usdt' : 'usdt-to-sphe';
            
            // Update UI
            if (swapDirection === 'usdt-to-sphe') {
                document.getElementById('from-token-symbol').textContent = 'USDT';
                document.getElementById('to-token-symbol').textContent = 'SPHE';
                document.querySelector('#from-token .token-icon').textContent = 'U';
                document.querySelector('#from-token span').textContent = 'USDT';
                document.querySelector('#to-token .token-icon').textContent = 'S';
                document.querySelector('#to-token span').textContent = 'SPHE';
                document.getElementById('from-balance').textContent = balances.usdt.toFixed(6);
                document.getElementById('to-balance').textContent = balances.sphe.toFixed(6);
            } else {
                document.getElementById('from-token-symbol').textContent = 'SPHE';
                document.getElementById('to-token-symbol').textContent = 'USDT';
                document.querySelector('#from-token .token-icon').textContent = 'S';
                document.querySelector('#from-token span').textContent = 'SPHE';
                document.querySelector('#to-token .token-icon').textContent = 'U';
                document.querySelector('#to-token span').textContent = 'USDT';
                document.getElementById('from-balance').textContent = balances.sphe.toFixed(6);
                document.getElementById('to-balance').textContent = balances.usdt.toFixed(6);
            }
            
            // Clear amounts and quote
            document.getElementById('from-amount').value = '';
            document.getElementById('to-amount').value = '';
            document.getElementById('swap-info').classList.remove('show');
            currentQuote = null;
            document.getElementById('swap-btn').disabled = true;
            document.getElementById('swap-btn').textContent = 'Ingresa un monto';
        });

        // Initialize
        loadBalances();
    </script>
</body>
</html>
