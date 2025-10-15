<?php
/**
 * SURVEYS PAGE
 * Lista de encuestas disponibles con opción de participar pagando SPHE
 */

session_start();
$page_title = "Encuestas Pagadas - Gana SPHE";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@metamask/sdk/dist/browser/index.js"></script>
    <style>
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700 py-4">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-blue-400">Encuestas Pagadas</h1>
                    <p class="text-sm text-gray-400">Participa y gana tokens SPHE</p>
                </div>
                <button id="connectWallet" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg font-semibold">
                    Conectar Wallet
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Wallet Info -->
        <div id="walletInfo" class="hidden bg-gray-800 rounded-lg p-4 mb-6 flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-400">Wallet conectado:</p>
                <p id="walletAddress" class="font-mono font-semibold"></p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-400">Balance SPHE:</p>
                <p id="spheBalance" class="text-xl font-bold text-blue-400">-</p>
            </div>
        </div>

        <!-- Surveys List -->
        <div id="surveysList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Loading state -->
            <div class="col-span-full text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-gray-700 border-t-blue-500"></div>
                <p class="mt-4 text-gray-400">Cargando encuestas...</p>
            </div>
        </div>
    </main>

    <!-- Payment Modal -->
    <div id="paymentModal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-8 max-w-md w-full mx-4">
            <h3 class="text-2xl font-bold mb-4" id="modalTitle">Pagar Encuesta</h3>

            <div id="modalContent">
                <!-- Survey Info -->
                <div class="bg-gray-900 rounded p-4 mb-4">
                    <p class="text-sm text-gray-400">Encuesta:</p>
                    <p class="font-semibold" id="modalSurveyTitle"></p>
                    <p class="text-sm text-gray-400 mt-2">Precio:</p>
                    <p class="text-2xl font-bold text-blue-400" id="modalPrice"></p>
                </div>

                <!-- Steps -->
                <div id="step1" class="mb-4">
                    <p class="text-sm text-gray-400 mb-2">Paso 1: Aprobar tokens SPHE</p>
                    <button id="approveBtn" class="w-full bg-yellow-600 hover:bg-yellow-700 py-3 rounded-lg font-semibold">
                        Aprobar SPHE
                    </button>
                </div>

                <div id="step2" class="mb-4 hidden">
                    <p class="text-sm text-gray-400 mb-2">Paso 2: Depositar tokens al contrato</p>
                    <button id="depositBtn" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-lg font-semibold">
                        Depositar y Participar
                    </button>
                </div>

                <!-- Transaction Status -->
                <div id="txStatus" class="hidden bg-gray-900 rounded p-4 mb-4">
                    <p class="text-sm text-gray-400">Estado de transacción:</p>
                    <p id="txStatusText" class="font-semibold"></p>
                    <a id="txLink" href="#" target="_blank" class="text-blue-400 text-sm hover:underline">Ver en PolygonScan</a>
                </div>
            </div>

            <button id="closeModal" class="w-full mt-4 bg-gray-700 hover:bg-gray-600 py-2 rounded-lg">
                Cerrar
            </button>
        </div>
    </div>

    <script>
        // Configuración
        const CONFIG = {
            SPHE_TOKEN: '0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b',
            ESCROW_CONTRACT: '', // TODO: Actualizar después del deploy
            CHAIN_ID: '0x89', // Polygon Mainnet (137 en hex)
            RPC_URL: 'https://polygon-mainnet.infura.io/v3/f210fc05834a4070871dbc89b2774608',
            EXPLORER_URL: 'https://polygonscan.com',
            API_BASE: '../api/'
        };

        // ABIs simplificados
        const ERC20_ABI = [
            {"inputs":[{"name":"spender","type":"address"},{"name":"amount","type":"uint256"}],"name":"approve","outputs":[{"name":"","type":"bool"}],"stateMutability":"nonpayable","type":"function"},
            {"inputs":[{"name":"account","type":"address"}],"name":"balanceOf","outputs":[{"name":"","type":"uint256"}],"stateMutability":"view","type":"function"},
            {"inputs":[{"name":"owner","type":"address"},{"name":"spender","type":"address"}],"name":"allowance","outputs":[{"name":"","type":"uint256"}],"stateMutability":"view","type":"function"}
        ];

        const ESCROW_ABI = [
            {"inputs":[{"name":"surveyId","type":"uint256"},{"name":"amount","type":"uint256"}],"name":"deposit","outputs":[],"stateMutability":"nonpayable","type":"function"}
        ];

        // Estado global
        let web3;
        let currentAccount;
        let currentSurvey;

        // Inicializar MetaMask SDK
        const MMSDK = new MetaMaskSDK.MetaMaskSDK({
            dappMetadata: {
                name: "thesocialmask Surveys",
                url: window.location.href,
            }
        });

        const ethereum = MMSDK.getProvider();

        // Conectar wallet
        document.getElementById('connectWallet').addEventListener('click', async () => {
            try {
                const accounts = await ethereum.request({ method: 'eth_requestAccounts' });
                currentAccount = accounts[0];

                // Verificar red
                const chainId = await ethereum.request({ method: 'eth_chainId' });
                if (chainId !== CONFIG.CHAIN_ID) {
                    await switchToPolygon();
                }

                showWalletInfo();
                await loadSPHEBalance();
            } catch (error) {
                console.error('Error connecting wallet:', error);
                alert('Error al conectar wallet: ' + error.message);
            }
        });

        // Cambiar a Polygon
        async function switchToPolygon() {
            try {
                await ethereum.request({
                    method: 'wallet_switchEthereumChain',
                    params: [{ chainId: CONFIG.CHAIN_ID }],
                });
            } catch (switchError) {
                if (switchError.code === 4902) {
                    await ethereum.request({
                        method: 'wallet_addEthereumChain',
                        params: [{
                            chainId: CONFIG.CHAIN_ID,
                            chainName: 'Polygon Mainnet',
                            rpcUrls: [CONFIG.RPC_URL],
                            nativeCurrency: { name: 'MATIC', symbol: 'MATIC', decimals: 18 },
                            blockExplorerUrls: [CONFIG.EXPLORER_URL]
                        }]
                    });
                }
            }
        }

        // Mostrar info de wallet
        function showWalletInfo() {
            document.getElementById('walletInfo').classList.remove('hidden');
            document.getElementById('walletAddress').textContent =
                currentAccount.slice(0, 6) + '...' + currentAccount.slice(-4);
            document.getElementById('connectWallet').textContent = 'Conectado';
            document.getElementById('connectWallet').disabled = true;
        }

        // Cargar balance de SPHE
        async function loadSPHEBalance() {
            try {
                const balance = await ethereum.request({
                    method: 'eth_call',
                    params: [{
                        to: CONFIG.SPHE_TOKEN,
                        data: '0x70a08231' + currentAccount.slice(2).padStart(64, '0')
                    }, 'latest']
                });

                const balanceWei = BigInt(balance);
                const balanceSPHE = Number(balanceWei) / 1e18;

                document.getElementById('spheBalance').textContent =
                    balanceSPHE.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' SPHE';
            } catch (error) {
                console.error('Error loading SPHE balance:', error);
            }
        }

        // Cargar encuestas
        async function loadSurveys() {
            try {
                const response = await fetch(CONFIG.API_BASE + 'get_surveys.php?status=active');
                const data = await response.json();

                if (data.success) {
                    renderSurveys(data.surveys);
                }
            } catch (error) {
                console.error('Error loading surveys:', error);
            }
        }

        // Renderizar encuestas
        function renderSurveys(surveys) {
            const container = document.getElementById('surveysList');

            if (surveys.length === 0) {
                container.innerHTML = '<div class="col-span-full text-center py-12"><p class="text-gray-400">No hay encuestas disponibles actualmente</p></div>';
                return;
            }

            container.innerHTML = surveys.map(survey => `
                <div class="bg-gray-800 rounded-lg p-6 card-hover">
                    <h3 class="text-xl font-bold mb-2">${escapeHtml(survey.title)}</h3>
                    <p class="text-sm text-gray-400 mb-4">${escapeHtml(survey.description || 'Sin descripción')}</p>

                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <p class="text-xs text-gray-500">Precio</p>
                            <p class="text-2xl font-bold text-blue-400">${survey.price} SPHE</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Prize Pool</p>
                            <p class="text-lg font-semibold">${survey.confirmed_pool_sphe || '0'} SPHE</p>
                        </div>
                    </div>

                    <div class="flex justify-between text-sm text-gray-400 mb-4">
                        <span>👥 ${survey.participant_count} participantes</span>
                        <span>🕒 Cierra: ${formatDate(survey.close_date)}</span>
                    </div>

                    ${survey.is_open && !survey.is_full ?
                        `<button onclick="openPaymentModal(${survey.id}, '${escapeHtml(survey.title)}', ${survey.price})"
                            class="w-full bg-blue-600 hover:bg-blue-700 py-2 rounded-lg font-semibold">
                            Participar Ahora
                        </button>` :
                        `<button disabled class="w-full bg-gray-700 py-2 rounded-lg font-semibold cursor-not-allowed">
                            ${survey.is_full ? 'Lleno' : 'Cerrado'}
                        </button>`
                    }
                </div>
            `).join('');
        }

        // Abrir modal de pago
        function openPaymentModal(surveyId, title, price) {
            if (!currentAccount) {
                alert('Por favor conecta tu wallet primero');
                return;
            }

            currentSurvey = { id: surveyId, title, price };

            document.getElementById('modalSurveyTitle').textContent = title;
            document.getElementById('modalPrice').textContent = price + ' SPHE';
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('step1').classList.remove('hidden');
            document.getElementById('step2').classList.add('hidden');
            document.getElementById('txStatus').classList.add('hidden');
        }

        // Cerrar modal
        document.getElementById('closeModal').addEventListener('click', () => {
            document.getElementById('paymentModal').classList.add('hidden');
        });

        // Aprobar SPHE
        document.getElementById('approveBtn').addEventListener('click', async () => {
            try {
                const amountWei = '0x' + (BigInt(currentSurvey.price * 1e18)).toString(16);

                // Encode approve(address,uint256)
                const data = '0x095ea7b3' +
                    CONFIG.ESCROW_CONTRACT.slice(2).padStart(64, '0') +
                    amountWei.slice(2).padStart(64, '0');

                const txHash = await ethereum.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: currentAccount,
                        to: CONFIG.SPHE_TOKEN,
                        data: data
                    }]
                });

                showTxStatus('Aprobación enviada...', txHash);

                // Esperar confirmación
                await waitForTx(txHash);

                document.getElementById('step1').classList.add('hidden');
                document.getElementById('step2').classList.remove('hidden');
                showTxStatus('Aprobación confirmada ✅', txHash);
            } catch (error) {
                console.error('Error approving:', error);
                alert('Error al aprobar tokens: ' + error.message);
            }
        });

        // Depositar
        document.getElementById('depositBtn').addEventListener('click', async () => {
            try {
                const surveyIdHex = '0x' + currentSurvey.id.toString(16).padStart(64, '0');
                const amountWei = '0x' + (BigInt(currentSurvey.price * 1e18)).toString(16);

                // Encode deposit(uint256,uint256)
                const data = '0x...' + // TODO: Calcular function selector
                    surveyIdHex.slice(2) +
                    amountWei.slice(2).padStart(64, '0');

                const txHash = await ethereum.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: currentAccount,
                        to: CONFIG.ESCROW_CONTRACT,
                        data: data
                    }]
                });

                showTxStatus('Depósito enviado...', txHash);

                // Registrar pago en backend
                await registerPayment(txHash);

                showTxStatus('Depósito confirmado ✅ Ya puedes responder la encuesta', txHash);

                // Recargar surveys y balance
                setTimeout(() => {
                    loadSurveys();
                    loadSPHEBalance();
                }, 3000);
            } catch (error) {
                console.error('Error depositing:', error);
                alert('Error al depositar: ' + error.message);
            }
        });

        // Registrar pago en backend
        async function registerPayment(txHash) {
            try {
                const response = await fetch(CONFIG.API_BASE + 'register_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        survey_id: currentSurvey.id,
                        tx_hash: txHash,
                        from_address: currentAccount,
                        amount: (currentSurvey.price * 1e18).toString()
                    })
                });

                const data = await response.json();
                console.log('Payment registered:', data);
            } catch (error) {
                console.error('Error registering payment:', error);
            }
        }

        // Mostrar estado de transacción
        function showTxStatus(text, txHash) {
            document.getElementById('txStatus').classList.remove('hidden');
            document.getElementById('txStatusText').textContent = text;
            document.getElementById('txLink').href = CONFIG.EXPLORER_URL + '/tx/' + txHash;
        }

        // Esperar confirmación de transacción
        async function waitForTx(txHash, timeout = 60000) {
            const start = Date.now();
            while (Date.now() - start < timeout) {
                const receipt = await ethereum.request({
                    method: 'eth_getTransactionReceipt',
                    params: [txHash]
                });

                if (receipt && receipt.status === '0x1') {
                    return true;
                }

                await new Promise(resolve => setTimeout(resolve, 2000));
            }
            throw new Error('Transaction timeout');
        }

        // Utilities
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = date - now;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));

            if (days > 0) return `${days}d`;
            const hours = Math.floor(diff / (1000 * 60 * 60));
            if (hours > 0) return `${hours}h`;
            return 'Pronto';
        }

        // Init
        loadSurveys();
        setInterval(loadSurveys, 30000); // Reload every 30s
    </script>
</body>
</html>
