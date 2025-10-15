/**
 * FASE 3.2 - Integración Frontend con Smart Contract Donations
 * 
 * Este archivo proporciona ejemplos de cómo integrar el contrato Donations
 * con el frontend de la aplicación.
 */

// ============================================================================
// 1. CONFIGURACIÓN INICIAL
// ============================================================================

// Importar ethers.js (debe estar en el HTML)
// <script src="https://cdn.ethers.io/lib/ethers-6.0.umd.min.js"></script>

// Direcciones de contratos (actualizar después del deployment)
const CONTRACTS = {
    DONATIONS: "0x0000000000000000000000000000000000000000", // TODO: Actualizar después del deployment
    TOKENS: {
        SPHE: "0x0000000000000000000000000000000000000000", // TODO: Actualizar
        WMATIC: "0x0d500B1d8E8eF31E21C99d1Db9A6444d3ADf1270" // Polygon Mainnet
    }
};

// ABI del contrato (cargar desde archivo JSON)
const DONATIONS_ABI = [
    "function donate(address recipient, address tokenAddress, uint256 amount, bool isAnonymous) external returns (uint256)",
    "function calculateFee(uint256 amount) public view returns (uint256)",
    "function calculateNetAmount(uint256 amount) public view returns (uint256)",
    "function getUserStats(address user) public view returns (tuple(uint256 totalDonated, uint256 totalReceived, uint256 donationsMade, uint256 donationsReceived))",
    "function getDonation(uint256 donationId) public view returns (tuple(address donor, address recipient, address tokenAddress, uint256 amount, uint256 fee, uint256 netAmount, bool isAnonymous, uint256 timestamp))",
    "function allowedTokens(address) public view returns (bool)",
    "function treasury() public view returns (address)",
    "function feePercentage() public view returns (uint256)",
    "function minDonation() public view returns (uint256)",
    "event DonationMade(uint256 indexed donationId, address indexed donor, address indexed recipient, address tokenAddress, uint256 amount, uint256 fee, uint256 netAmount, bool isAnonymous, uint256 timestamp)"
];

const ERC20_ABI = [
    "function approve(address spender, uint256 amount) external returns (bool)",
    "function allowance(address owner, address spender) external view returns (uint256)",
    "function balanceOf(address account) external view returns (uint256)",
    "function decimals() external view returns (uint8)",
    "function symbol() external view returns (string)"
];

// ============================================================================
// 2. CLASE DONATIONS MANAGER
// ============================================================================

class DonationsManager {
    constructor() {
        this.provider = null;
        this.signer = null;
        this.donationsContract = null;
        this.userAddress = null;
    }

    /**
     * Conectar con MetaMask y inicializar contratos
     */
    async connect() {
        if (!window.ethereum) {
            throw new Error("MetaMask no está instalado");
        }

        // Solicitar conexión
        await window.ethereum.request({ method: 'eth_requestAccounts' });
        
        // Crear provider y signer
        this.provider = new ethers.BrowserProvider(window.ethereum);
        this.signer = await this.provider.getSigner();
        this.userAddress = await this.signer.getAddress();

        // Inicializar contrato
        this.donationsContract = new ethers.Contract(
            CONTRACTS.DONATIONS,
            DONATIONS_ABI,
            this.signer
        );

        // Verificar red (debe ser Polygon)
        const network = await this.provider.getNetwork();
        if (network.chainId !== 137n) { // 137 = Polygon Mainnet
            throw new Error("Por favor conecta a Polygon Mainnet");
        }

        console.log("✅ Conectado a Polygon con cuenta:", this.userAddress);
        return this.userAddress;
    }

    /**
     * Obtener información de tokens desde PHP backend
     */
    async getAvailableTokens() {
        try {
            const response = await fetch('/api/donations/token-config.php');
            const data = await response.json();
            
            if (data.success) {
                return data.tokens.filter(t => t.active);
            }
            throw new Error(data.message);
        } catch (error) {
            console.error("Error al obtener tokens:", error);
            return [];
        }
    }

    /**
     * Verificar si un token está permitido en el contrato
     */
    async isTokenAllowed(tokenAddress) {
        return await this.donationsContract.allowedTokens(tokenAddress);
    }

    /**
     * Obtener balance de un token
     */
    async getTokenBalance(tokenAddress) {
        const tokenContract = new ethers.Contract(tokenAddress, ERC20_ABI, this.signer);
        const balance = await tokenContract.balanceOf(this.userAddress);
        const decimals = await tokenContract.decimals();
        return ethers.formatUnits(balance, decimals);
    }

    /**
     * Calcular fee y net amount
     */
    async calculateDonationAmounts(amount) {
        const amountWei = ethers.parseEther(amount.toString());
        const fee = await this.donationsContract.calculateFee(amountWei);
        const netAmount = await this.donationsContract.calculateNetAmount(amountWei);
        
        return {
            amount: ethers.formatEther(amountWei),
            fee: ethers.formatEther(fee),
            netAmount: ethers.formatEther(netAmount),
            feePercentage: 2.5 // 250 basis points = 2.5%
        };
    }

    /**
     * Aprobar tokens para el contrato
     */
    async approveToken(tokenAddress, amount) {
        const tokenContract = new ethers.Contract(tokenAddress, ERC20_ABI, this.signer);
        const amountWei = ethers.parseEther(amount.toString());
        
        console.log(`Aprobando ${amount} tokens...`);
        const tx = await tokenContract.approve(CONTRACTS.DONATIONS, amountWei);
        
        console.log("Esperando confirmación...");
        const receipt = await tx.wait();
        
        console.log("✅ Tokens aprobados:", receipt.hash);
        return receipt;
    }

    /**
     * Verificar allowance actual
     */
    async checkAllowance(tokenAddress) {
        const tokenContract = new ethers.Contract(tokenAddress, ERC20_ABI, this.signer);
        const allowance = await tokenContract.allowance(this.userAddress, CONTRACTS.DONATIONS);
        return ethers.formatEther(allowance);
    }

    /**
     * Hacer una donación
     */
    async makeDonation(recipientAddress, tokenAddress, amount, isAnonymous = false) {
        try {
            // Validaciones
            if (!ethers.isAddress(recipientAddress)) {
                throw new Error("Dirección de receptor inválida");
            }
            if (recipientAddress.toLowerCase() === this.userAddress.toLowerCase()) {
                throw new Error("No puedes donarte a ti mismo");
            }

            const amountWei = ethers.parseEther(amount.toString());

            // Verificar balance
            const balance = await this.getTokenBalance(tokenAddress);
            if (parseFloat(balance) < parseFloat(amount)) {
                throw new Error("Balance insuficiente");
            }

            // Verificar allowance
            const allowance = await this.checkAllowance(tokenAddress);
            if (parseFloat(allowance) < parseFloat(amount)) {
                console.log("Aprobando tokens primero...");
                await this.approveToken(tokenAddress, amount);
            }

            // Hacer donación
            console.log(`Enviando donación de ${amount} tokens...`);
            const tx = await this.donationsContract.donate(
                recipientAddress,
                tokenAddress,
                amountWei,
                isAnonymous
            );

            console.log("Esperando confirmación...");
            const receipt = await tx.wait();

            // Obtener el evento DonationMade
            const donationEvent = receipt.logs
                .map(log => {
                    try {
                        return this.donationsContract.interface.parseLog(log);
                    } catch (e) {
                        return null;
                    }
                })
                .find(event => event && event.name === 'DonationMade');

            if (donationEvent) {
                const donationId = donationEvent.args[0];
                console.log("✅ Donación exitosa! ID:", donationId.toString());
                
                return {
                    success: true,
                    transactionHash: receipt.hash,
                    donationId: donationId.toString(),
                    blockNumber: receipt.blockNumber
                };
            }

            throw new Error("No se pudo obtener el ID de la donación");

        } catch (error) {
            console.error("❌ Error al hacer donación:", error);
            throw error;
        }
    }

    /**
     * Obtener estadísticas del usuario
     */
    async getUserStats(address = null) {
        const userAddr = address || this.userAddress;
        const stats = await this.donationsContract.getUserStats(userAddr);
        
        return {
            totalDonated: ethers.formatEther(stats.totalDonated),
            totalReceived: ethers.formatEther(stats.totalReceived),
            donationsMade: stats.donationsMade.toString(),
            donationsReceived: stats.donationsReceived.toString()
        };
    }

    /**
     * Obtener información de una donación específica
     */
    async getDonationInfo(donationId) {
        const donation = await this.donationsContract.getDonation(donationId);
        
        return {
            donor: donation.donor,
            recipient: donation.recipient,
            tokenAddress: donation.tokenAddress,
            amount: ethers.formatEther(donation.amount),
            fee: ethers.formatEther(donation.fee),
            netAmount: ethers.formatEther(donation.netAmount),
            isAnonymous: donation.isAnonymous,
            timestamp: new Date(Number(donation.timestamp) * 1000).toISOString()
        };
    }

    /**
     * Escuchar eventos de donaciones en tiempo real
     */
    listenToDonations(callback) {
        this.donationsContract.on("DonationMade", (
            donationId,
            donor,
            recipient,
            tokenAddress,
            amount,
            fee,
            netAmount,
            isAnonymous,
            timestamp,
            event
        ) => {
            callback({
                donationId: donationId.toString(),
                donor,
                recipient,
                tokenAddress,
                amount: ethers.formatEther(amount),
                fee: ethers.formatEther(fee),
                netAmount: ethers.formatEther(netAmount),
                isAnonymous,
                timestamp: new Date(Number(timestamp) * 1000).toISOString(),
                transactionHash: event.log.transactionHash
            });
        });
    }

    /**
     * Dejar de escuchar eventos
     */
    stopListening() {
        this.donationsContract.removeAllListeners("DonationMade");
    }
}

// ============================================================================
// 3. EJEMPLO DE USO EN EL FRONTEND
// ============================================================================

// Inicializar el manager
const donationsManager = new DonationsManager();

// Función para conectar wallet
async function connectWallet() {
    try {
        const address = await donationsManager.connect();
        document.getElementById('wallet-address').textContent = 
            `${address.substring(0, 6)}...${address.substring(38)}`;
        
        // Cargar tokens disponibles
        await loadAvailableTokens();
        
        // Cargar estadísticas del usuario
        await loadUserStats();
        
    } catch (error) {
        alert("Error al conectar: " + error.message);
    }
}

// Función para cargar tokens disponibles
async function loadAvailableTokens() {
    const tokens = await donationsManager.getAvailableTokens();
    const select = document.getElementById('token-select');
    
    select.innerHTML = '';
    for (const token of tokens) {
        const option = document.createElement('option');
        option.value = token.contract_address;
        option.textContent = `${token.symbol} (${token.name})`;
        select.appendChild(option);
    }
}

// Función para calcular preview de donación
async function calculatePreview() {
    const amount = document.getElementById('amount').value;
    if (!amount || parseFloat(amount) <= 0) return;
    
    try {
        const preview = await donationsManager.calculateDonationAmounts(amount);
        
        document.getElementById('preview-amount').textContent = preview.amount;
        document.getElementById('preview-fee').textContent = `${preview.fee} (${preview.feePercentage}%)`;
        document.getElementById('preview-net').textContent = preview.netAmount;
        
    } catch (error) {
        console.error("Error al calcular preview:", error);
    }
}

// Función para hacer donación
async function submitDonation() {
    const recipient = document.getElementById('recipient').value;
    const token = document.getElementById('token-select').value;
    const amount = document.getElementById('amount').value;
    const isAnonymous = document.getElementById('anonymous').checked;
    
    try {
        // Deshabilitar botón
        const button = document.getElementById('donate-button');
        button.disabled = true;
        button.textContent = 'Procesando...';
        
        // Hacer donación
        const result = await donationsManager.makeDonation(
            recipient,
            token,
            amount,
            isAnonymous
        );
        
        // Guardar en backend
        await saveDonationToBackend(result);
        
        // Mostrar éxito
        alert(`¡Donación exitosa! TX: ${result.transactionHash}`);
        
        // Recargar estadísticas
        await loadUserStats();
        
        // Limpiar formulario
        document.getElementById('donation-form').reset();
        
    } catch (error) {
        alert("Error: " + error.message);
    } finally {
        const button = document.getElementById('donate-button');
        button.disabled = false;
        button.textContent = 'Donar';
    }
}

// Función para guardar en backend
async function saveDonationToBackend(donationData) {
    try {
        const response = await fetch('/api/donations/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(donationData)
        });
        
        const result = await response.json();
        if (!result.success) {
            console.error("Error al guardar en BD:", result.message);
        }
    } catch (error) {
        console.error("Error al guardar en backend:", error);
    }
}

// Función para cargar estadísticas
async function loadUserStats() {
    try {
        const stats = await donationsManager.getUserStats();
        
        document.getElementById('total-donated').textContent = stats.totalDonated;
        document.getElementById('total-received').textContent = stats.totalReceived;
        document.getElementById('donations-made').textContent = stats.donationsMade;
        document.getElementById('donations-received').textContent = stats.donationsReceived;
        
    } catch (error) {
        console.error("Error al cargar stats:", error);
    }
}

// Escuchar eventos en tiempo real
donationsManager.listenToDonations((donation) => {
    console.log("Nueva donación detectada:", donation);
    
    // Actualizar UI si es relevante para el usuario
    if (donation.donor.toLowerCase() === donationsManager.userAddress.toLowerCase() ||
        donation.recipient.toLowerCase() === donationsManager.userAddress.toLowerCase()) {
        loadUserStats();
    }
});

// ============================================================================
// 4. HTML EJEMPLO
// ============================================================================

/*
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Donaciones</title>
    <script src="https://cdn.ethers.io/lib/ethers-6.0.umd.min.js"></script>
</head>
<body>
    <div id="app">
        <h1>Sistema de Donaciones SPHE</h1>
        
        <!-- Botón de conexión -->
        <button onclick="connectWallet()">Conectar Wallet</button>
        <p id="wallet-address"></p>
        
        <!-- Formulario de donación -->
        <form id="donation-form" onsubmit="event.preventDefault(); submitDonation();">
            <label>Destinatario:</label>
            <input type="text" id="recipient" placeholder="0x..." required>
            
            <label>Token:</label>
            <select id="token-select" required></select>
            
            <label>Cantidad:</label>
            <input type="number" id="amount" step="0.01" min="0.01" 
                   oninput="calculatePreview()" required>
            
            <label>
                <input type="checkbox" id="anonymous">
                Donación anónima
            </label>
            
            <!-- Preview -->
            <div id="preview">
                <p>Cantidad: <span id="preview-amount">0</span></p>
                <p>Fee: <span id="preview-fee">0</span></p>
                <p>Recibirá: <span id="preview-net">0</span></p>
            </div>
            
            <button type="submit" id="donate-button">Donar</button>
        </form>
        
        <!-- Estadísticas -->
        <div id="stats">
            <h2>Tus Estadísticas</h2>
            <p>Total Donado: <span id="total-donated">0</span></p>
            <p>Total Recibido: <span id="total-received">0</span></p>
            <p>Donaciones Hechas: <span id="donations-made">0</span></p>
            <p>Donaciones Recibidas: <span id="donations-received">0</span></p>
        </div>
    </div>
    
    <script src="donations-integration.js"></script>
</body>
</html>
*/

// ============================================================================
// EXPORTAR PARA USO EN MÓDULOS
// ============================================================================

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { DonationsManager, CONTRACTS, DONATIONS_ABI, ERC20_ABI };
}
