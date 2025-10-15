<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Crear Cuenta - The Social Mask</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="../assets/css/responsive.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@latest/dist/web3.min.js"></script>
    <script src="https://unpkg.com/@walletconnect/web3-provider@1.8.0/dist/umd/index.min.js"></script>
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
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'inter', sans-serif;
        }
        .status-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        .status-error {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        .status-info {
            background-color: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
            color: #3B82F6;
        }
    </style>
</head>
<body class="bg-brand-bg-primary text-brand-text-primary">

    <?php include '../components/navbar.php'; ?>

    <!-- Main Content -->
    <div class="min-h-screen flex items-center justify-center px-4 py-16 js-scroll-animation">
        <div class="w-full max-w-lg">
            <!-- Header Section -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold mb-3 text-brand-text-primary">
                    Crea tu Cuenta en <span class="text-brand-accent">The Social Mask</span>
                </h1>
                <p class="text-brand-text-secondary text-lg">Tu identidad descentralizada para el nuevo internet.</p>
            </div>

            <!-- Main Card -->
            <div class="bg-brand-bg-secondary border border-brand-border rounded-2xl p-8">
                <!-- Status Display -->
                <div id="status-display" class="hidden mb-4 p-3 rounded-xl border">
                    <div class="flex items-center space-x-3">
                        <div id="status-icon" class="w-4 h-4 rounded-full"></div>
                        <span id="status-text" class="font-medium text-sm"></span>
                    </div>
                </div>

                <!-- Username Section -->
                <div class="mb-6">
                    <label for="username" class="block text-brand-text-primary font-medium text-sm mb-2">Elige tu Usuario</label>
                    <div class="relative">
                        <input
                            type="text"
                            id="username"
                            placeholder="Introduce tu usuario"
                            class="bg-brand-bg-primary border border-brand-border w-full px-4 py-3 rounded-lg text-brand-text-primary placeholder-brand-text-secondary focus:outline-none focus:ring-2 focus:ring-brand-accent focus:border-transparent transition"
                            maxlength="20"
                            required
                        >
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <div id="username-status" class="text-xs"></div>
                        </div>
                    </div>
                    <p class="text-brand-text-secondary text-xs mt-1">3-20 caracteres, solo letras, números y guiones bajos.</p>
                </div>

                <!-- Wallet Connection Section -->
                <div class="mb-6">
                    <h3 class="text-brand-text-primary font-medium text-sm mb-4">Conecta tu Wallet</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Wallet Buttons -->
                        <button onclick="connectWallet('metamask')" class="bg-brand-bg-primary border border-brand-border rounded-xl p-4 text-left transition-all hover:border-brand-accent">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-8 h-8 bg-brand-bg-secondary rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-brand-text-primary" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.171-1.419-.513-1.9L17.89 4.44c-.171-.24-.513-.419-.855-.419H6.965c-.342 0-.684.179-.855.419L2.25 10.35c-.342.481-.513 1.122-.513 1.9 0 .78.171 1.419.513 1.9l3.86 5.91c.171.24.513.419.855.419h10.07c.342 0 .684-.179.855-.419l3.86-5.91c.342-.481.513-1.122.513-1.9z"/></svg>
                                </div>
                                <div id="metamask-indicator" class="w-3 h-3 border-2 border-brand-border rounded-full"></div>
                            </div>
                            <h4 class="text-brand-text-primary font-semibold text-sm">MetaMask</h4>
                        </button>
                        <button onclick="connectWallet('walletconnect')" class="bg-brand-bg-primary border border-brand-border rounded-xl p-4 text-left transition-all hover:border-brand-accent">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-8 h-8 bg-brand-bg-secondary rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-brand-text-primary" viewBox="0 0 24 24" fill="currentColor"><path d="M5.1 8.9c3.6-3.5 9.4-3.5 13 0l.4.4c.2.2.2.5 0 .7l-1.4 1.4c-.1.1-.3.1-.4 0l-.5-.5c-2.5-2.4-6.5-2.4-9 0l-.6.5c-.1.1-.3.1-.4 0L4.8 10c-.2-.2-.2-.5 0-.7l.3-.4zm16 2.9l1.2 1.2c.2.2.2.5 0 .7l-5.4 5.3c-.2.2-.5.2-.7 0L12 14.8c-.1-.1-.2-.1-.2 0L7.6 19c-.2.2-.5.2-.7 0L1.5 13.7c-.2-.2-.2-.5 0-.7l1.2-1.2c.2-.2.5-.2.7 0l4.2 4.1c.1.1.2.1.2 0l4.2-4.1c.2-.2.5-.2.7 0l4.2 4.1c.1.1.2.1.2 0l4.2-4.1c.2-.2.5-.2.7 0z"/></svg>
                                </div>
                                <div id="walletconnect-indicator" class="w-3 h-3 border-2 border-brand-border rounded-full"></div>
                            </div>
                            <h4 class="text-brand-text-primary font-semibold text-sm">WalletConnect</h4>
                        </button>
                        <button onclick="connectWallet('coinbase')" class="bg-brand-bg-primary border border-brand-border rounded-xl p-4 text-left transition-all hover:border-brand-accent">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-8 h-8 bg-brand-bg-secondary rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-brand-text-primary" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm-1.5 13.5h3v3h-3v-3z"/></svg>
                                </div>
                                <div id="coinbase-indicator" class="w-3 h-3 border-2 border-brand-border rounded-full"></div>
                            </div>
                            <h4 class="text-brand-text-primary font-semibold text-sm">Coinbase</h4>
                        </button>
                        <button onclick="connectWallet('trust')" class="bg-brand-bg-primary border border-brand-border rounded-xl p-4 text-left transition-all hover:border-brand-accent">
                            <div class="flex items-center justify-between mb-2">
                                <div class="w-8 h-8 bg-brand-bg-secondary rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-brand-text-primary" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0L3 6v6c0 7.5 6 12 9 12s9-4.5 9-12V6l-9-6zm-1 16l-5-5 1.41-1.41L11 13.17l5.59-5.58L18 9l-7 7z"/></svg>
                                </div>
                                <div id="trust-indicator" class="w-3 h-3 border-2 border-brand-border rounded-full"></div>
                            </div>
                            <h4 class="text-brand-text-primary font-semibold text-sm">Trust Wallet</h4>
                        </button>
                    </div>
                </div>

                <!-- Create Account Button -->
                <button
                    id="create-btn"
                    onclick="createAccount()"
                    disabled
                    class="bg-brand-accent w-full py-3 rounded-lg text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-opacity"
                >
                    <span id="btn-text">Introduce un usuario y conecta tu wallet</span>
                </button>

                <!-- Login Link -->
                <div class="text-center mt-6 pt-6 border-t border-brand-border">
                    <p class="text-brand-text-secondary text-sm">
                        ¿Ya tienes una cuenta?
                        <a href="/login" class="text-brand-accent font-medium hover:underline">Inicia sesión</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentWallet = null;
        let walletAddress = null;
        let web3 = null;

        // Log to verify script is loading
        console.log('Register.php script loaded');

        // --- ANIMATION ---
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');

            const observerOptions = { root: null, rootMargin: '0px', threshold: 0.1 };
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in-up');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.js-scroll-animation').forEach(el => {
                observer.observe(el);
            });
        });

        // --- STATUS & UI ---
        function showStatus(message, type = 'info') {
            const statusDisplay = document.getElementById('status-display');
            const statusIcon = document.getElementById('status-icon');
            const statusText = document.getElementById('status-text');
            statusDisplay.className = `mb-4 p-3 rounded-xl border status-${type}`;
            statusDisplay.classList.remove('hidden');
            const icons = { success: 'bg-brand-success', error: 'bg-brand-error', info: 'bg-brand-accent animate-pulse' };
            statusIcon.className = `w-4 h-4 rounded-full ${icons[type] || icons.info}`;
            statusText.textContent = message;
        }

        document.getElementById('username').addEventListener('input', function() {
            const username = this.value.trim();
            const statusEl = document.getElementById('username-status');
            if (username.length === 0) {
                statusEl.textContent = '';
            } else if (username.length < 3) {
                statusEl.textContent = 'Muy corto'; statusEl.className = 'text-xs text-brand-error';
            } else if (username.length > 20) {
                statusEl.textContent = 'Muy largo'; statusEl.className = 'text-xs text-brand-error';
            } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                statusEl.textContent = 'Inválido'; statusEl.className = 'text-xs text-brand-error';
            } else {
                statusEl.textContent = '✓'; statusEl.className = 'text-xs text-brand-success';
            }
            updateCreateButton();
        });

        function updateCreateButton() {
            const username = document.getElementById('username').value.trim();
            const createBtn = document.getElementById('create-btn');
            const btnText = document.getElementById('btn-text');
            const isUsernameValid = username.length >= 3 && username.length <= 20 && /^[a-zA-Z0-9_]+$/.test(username);

            if (isUsernameValid && walletAddress) {
                createBtn.disabled = false;
                btnText.textContent = 'Crear Cuenta';
            } else {
                createBtn.disabled = true;
                if (!isUsernameValid) btnText.textContent = 'Introduce un usuario válido';
                else btnText.textContent = 'Conecta tu Wallet';
            }
        }

        function resetWalletIndicators() {
            ['metamask', 'walletconnect', 'coinbase', 'trust'].forEach(wallet => updateWalletIndicator(wallet, false));
        }

        function updateWalletIndicator(walletType, connected) {
            const indicator = document.getElementById(`${walletType}-indicator`);
            if (indicator) {
                indicator.className = connected ? 'w-3 h-3 bg-brand-success rounded-full' : 'w-3 h-3 border-2 border-brand-border rounded-full';
            }
        }

        // --- WALLET & REGISTRATION LOGIC ---
        // Log when function is defined
        console.log('Defining connectWallet function');

        async function connectWallet(walletType) {
            console.log('connectWallet called with:', walletType);

            try {
                showStatus(`Conectando a ${walletType}...`, 'info');
                resetWalletIndicators();

                if (walletType === 'metamask') {
                    // Check if MetaMask is installed
                    if (typeof window.ethereum === 'undefined') {
                        throw new Error('MetaMask no está instalado. Por favor instala MetaMask desde metamask.io');
                    }

                    console.log('MetaMask detected, requesting accounts...');

                    // Request account access
                    const accounts = await window.ethereum.request({
                        method: 'eth_requestAccounts'
                    });

                    console.log('Accounts received:', accounts);

                    if (!accounts || accounts.length === 0) {
                        throw new Error('No se pudo acceder a las cuentas de MetaMask');
                    }

                    // Set wallet info
                    walletAddress = accounts[0].toLowerCase();
                    currentWallet = walletType;
                    web3 = new Web3(window.ethereum);

                    console.log('Wallet connected:', walletAddress);

                    // Validate address format
                    if (!walletAddress.match(/^0x[a-fA-F0-9]{40}$/)) {
                        throw new Error('Dirección de wallet inválida recibida');
                    }

                    showStatus(`Conectado: ${walletAddress.substring(0, 6)}...${walletAddress.substring(38)}`, 'success');
                    updateWalletIndicator(walletType, true);
                    updateCreateButton();

                } else if (walletType === 'walletconnect') {
                    throw new Error('WalletConnect - Próximamente disponible');
                } else if (walletType === 'coinbase') {
                    throw new Error('Coinbase Wallet - Próximamente disponible');
                } else if (walletType === 'trust') {
                    throw new Error('Trust Wallet - Próximamente disponible');
                } else {
                    throw new Error('Tipo de wallet no soportado');
                }

            } catch (error) {
                console.error('Wallet connection error:', error);
                showStatus(error.message || 'Error conectando wallet', 'error');
                resetWalletIndicators();
                walletAddress = null;
                currentWallet = null;
                updateCreateButton();
            }
        }

        async function createAccount() {
            const username = document.getElementById('username').value.trim();
            if (!username || !walletAddress) {
                showStatus('Por favor, completa todos los campos', 'error');
                return;
            }
            try {
                document.getElementById('btn-text').textContent = 'Creando...';
                document.getElementById('create-btn').disabled = true;

                const nonceResponse = await fetch('../api/get_nonce.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ wallet_address: walletAddress })
                });

                if (!nonceResponse.ok) {
                    throw new Error(`HTTP Error: ${nonceResponse.status}`);
                }

                const responseText = await nonceResponse.text();
                if (!responseText) {
                    throw new Error('Empty response from server');
                }

                let nonceResult;
                try {
                    nonceResult = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse JSON response:', responseText);
                    throw new Error('Invalid server response format');
                }

                if (!nonceResult.success) throw new Error(nonceResult.message);

                showStatus('Por favor, firma el mensaje en tu wallet...', 'info');
                const message = `Registrarse en The Social Mask con el usuario: ${username}\nNonce: ${nonceResult.nonce}`;

                // Use the correct signing method based on wallet type
                let signature;
                if (currentWallet === 'metamask' && window.ethereum) {
                    // For MetaMask, use the proper signing method
                    signature = await window.ethereum.request({
                        method: 'personal_sign',
                        params: [message, walletAddress]
                    });
                } else {
                    // Fallback to web3.eth.personal.sign
                    signature = await web3.eth.personal.sign(message, walletAddress);
                }

                const response = await fetch('../api/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username, wallet_address: walletAddress, wallet_type: currentWallet,
                        signature, message, nonce: nonceResult.nonce
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }

                const registerResponseText = await response.text();
                if (!registerResponseText) {
                    throw new Error('Empty response from server');
                }

                let result;
                try {
                    result = JSON.parse(registerResponseText);
                } catch (parseError) {
                    console.error('Failed to parse JSON response:', registerResponseText);
                    throw new Error('Invalid server response format');
                }

                if (result.success) {
                    showStatus('¡Éxito! Redirigiendo...', 'success');
                    setTimeout(() => window.location.href = '../index.php', 2000);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                showStatus(error.message, 'error');
                document.getElementById('btn-text').textContent = 'Crear Cuenta';
                updateCreateButton();
            }
        }
    </script>
</body>
</html>