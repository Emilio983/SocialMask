/**
 * Gelato Relay SDK - Gasless Transactions
 * Permite a usuarios comprar contenido sin pagar gas
 * Documentación: https://docs.gelato.network/developer-services/relay
 */

class GelatoRelay {
    constructor() {
        this.relayUrl = "https://relay.gelato.digital";
        this.taskStatusUrl = "https://relay.gelato.digital/tasks/status";
        this.apiKey = null;
        this.chainId = null;
        this.isInitialized = false;
    }
    
    /**
     * Inicializar Gelato Relay
     * @param {string} apiKey - API Key de Gelato
     */
    async initialize(apiKey) {
        this.apiKey = apiKey;
        
        if (!window.smartWalletProvider) {
            throw new Error("Smart Wallet no detectada");
        }
        
        // Obtener chainId
        const chainIdHex = await window.smartWalletProvider.request({ method: 'eth_chainId' });
        this.chainId = parseInt(chainIdHex, 16);
        
        this.isInitialized = true;
        console.log(`✅ Gelato Relay initialized on chain ${this.chainId}`);
    }
    
    /**
     * Verificar si Gelato Relay está disponible
     * @returns {boolean}
     */
    isAvailable() {
        return this.isInitialized && this.apiKey !== null;
    }
    
    /**
     * Obtener dirección del usuario
     * @returns {Promise<string>}
     */
    async getUserAddress() {
        const accounts = await window.smartWalletProvider.request({ 
            method: 'eth_requestAccounts' 
        });
        return accounts[0];
    }
    
    /**
     * Enviar transacción patrocinada (gasless)
     * @param {string} target - Dirección del contrato
     * @param {string} data - Data encodada de la función
     * @param {string} gasLimit - Límite de gas (opcional)
     * @returns {Promise<string>} taskId
     */
    async sponsoredCall(target, data, gasLimit = "500000") {
        if (!this.isAvailable()) {
            throw new Error("Gelato Relay no inicializado");
        }
        
        const user = await this.getUserAddress();
        
        const request = {
            chainId: this.chainId,
            target: target,
            data: data,
            user: user,
            gasLimit: gasLimit
        };
        
        console.log("📤 Sending sponsored call:", request);
        
        try {
            const response = await fetch(`${this.relayUrl}/relays/v2/sponsored-call`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.apiKey}`
                },
                body: JSON.stringify(request)
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(`Gelato API error: ${errorData.message || response.statusText}`);
            }
            
            const result = await response.json();
            console.log("✅ Task created:", result.taskId);
            
            return result.taskId;
            
        } catch (error) {
            console.error("❌ Gelato relay error:", error);
            throw error;
        }
    }
    
    /**
     * Obtener estado de una tarea
     * @param {string} taskId - ID de la tarea
     * @returns {Promise<object>}
     */
    async getTaskStatus(taskId) {
        try {
            const response = await fetch(`${this.taskStatusUrl}/${taskId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const status = await response.json();
            return status.task;
            
        } catch (error) {
            console.error("❌ Error getting task status:", error);
            throw error;
        }
    }
    
    /**
     * Esperar a que una tarea se complete
     * @param {string} taskId - ID de la tarea
     * @param {number} maxWait - Tiempo máximo de espera en ms (default: 60s)
     * @param {function} onStatusUpdate - Callback para updates de estado
     * @returns {Promise<object>}
     */
    async waitForTask(taskId, maxWait = 60000, onStatusUpdate = null) {
        const startTime = Date.now();
        const pollInterval = 2000; // 2 segundos
        
        console.log(`⏳ Waiting for task ${taskId}...`);
        
        while (Date.now() - startTime < maxWait) {
            try {
                const status = await this.getTaskStatus(taskId);
                
                // Callback opcional
                if (onStatusUpdate) {
                    onStatusUpdate(status);
                }
                
                console.log(`📊 Task status: ${status.taskState}`);
                
                // Estados finales
                if (status.taskState === 'ExecSuccess') {
                    console.log(`✅ Task completed: ${status.transactionHash}`);
                    return status;
                }
                
                if (status.taskState === 'Cancelled') {
                    throw new Error('Task fue cancelada');
                }
                
                if (status.taskState === 'ExecReverted') {
                    throw new Error(`Task reverted: ${status.lastCheckMessage || 'Unknown error'}`);
                }
                
                // Estados intermedios: CheckPending, ExecPending, WaitingForConfirmation
                // Continuar esperando...
                
            } catch (error) {
                // Si es error de estado final, lanzar
                if (error.message.includes('cancelled') || error.message.includes('reverted')) {
                    throw error;
                }
                // Si es error de red, continuar intentando
                console.warn("⚠️ Error checking status, retrying...");
            }
            
            await new Promise(resolve => setTimeout(resolve, pollInterval));
        }
        
        throw new Error('Task timeout - no se completó en el tiempo esperado');
    }
    
    /**
     * Enviar transacción con firma del usuario (gasless con meta-tx)
     * @param {string} target - Dirección del contrato
     * @param {string} data - Data encodada
     * @returns {Promise<string>} taskId
     */
    async signedCall(target, data) {
        if (!this.isAvailable()) {
            throw new Error("Gelato Relay no inicializado");
        }
        
        const user = await this.getUserAddress();
        
        // Crear mensaje para firmar
        const message = {
            chainId: this.chainId,
            target: target,
            data: data,
            user: user
        };
        
        // Firmar con Smart Wallet
        const signature = await this.signMessage(JSON.stringify(message));
        
        const request = {
            ...message,
            signature: signature
        };
        
        try {
            const response = await fetch(`${this.relayUrl}/relays/v2/signed-call`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(request)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            return result.taskId;
            
        } catch (error) {
            console.error("❌ Signed call error:", error);
            throw error;
        }
    }
    
    /**
     * Firmar mensaje con Smart Wallet
     * @param {string} message - Mensaje a firmar
     * @returns {Promise<string>}
     */
    async signMessage(message) {
        const user = await this.getUserAddress();
        
        const signature = await window.smartWalletProvider.request({
            method: 'personal_sign',
            params: [message, user]
        });
        
        return signature;
    }
    
    /**
     * Obtener balance de Gelato (para sponsored calls)
     * @returns {Promise<object>}
     */
    async getBalance() {
        if (!this.apiKey) {
            throw new Error("API Key no configurado");
        }
        
        try {
            const response = await fetch(`${this.relayUrl}/relays/v2/balance`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${this.apiKey}`
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const balance = await response.json();
            return balance;
            
        } catch (error) {
            console.error("❌ Error getting balance:", error);
            throw error;
        }
    }
    
    /**
     * Estimar gas de una transacción
     * @param {string} target - Dirección del contrato
     * @param {string} data - Data encodada
     * @returns {Promise<number>}
     */
    async estimateGas(target, data) {
        try {
            const user = await this.getUserAddress();
            
            const gasEstimate = await window.smartWalletProvider.request({
                method: 'eth_estimateGas',
                params: [{
                    from: user,
                    to: target,
                    data: data
                }]
            });
            
            // Convertir de hex a decimal
            const gasDecimal = parseInt(gasEstimate, 16);
            
            // Agregar 20% de buffer
            const gasWithBuffer = Math.ceil(gasDecimal * 1.2);
            
            console.log(`⛽ Gas estimado: ${gasDecimal} (con buffer: ${gasWithBuffer})`);
            
            return gasWithBuffer;
            
        } catch (error) {
            console.error("❌ Error estimating gas:", error);
            // Retornar default si falla
            return 500000;
        }
    }
    
    /**
     * Verificar si una transacción es elegible para relay
     * @param {string} target - Dirección del contrato
     * @param {string} data - Data encodada
     * @returns {Promise<boolean>}
     */
    async isRelayable(target, data) {
        try {
            const gas = await this.estimateGas(target, data);
            
            // Gelato tiene un límite de gas (típicamente 10M)
            const MAX_GAS = 10000000;
            
            if (gas > MAX_GAS) {
                console.warn(`⚠️ Gas demasiado alto: ${gas} > ${MAX_GAS}`);
                return false;
            }
            
            return true;
            
        } catch (error) {
            console.error("❌ Error checking relayability:", error);
            return false;
        }
    }
    
    /**
     * Helper: Crear status display HTML
     * @param {string} status - Estado de la tarea
     * @returns {string} HTML
     */
    getStatusHTML(status) {
        const statusConfig = {
            'CheckPending': { icon: '⏳', text: 'Verificando...', class: 'warning' },
            'ExecPending': { icon: '🔄', text: 'Ejecutando...', class: 'info' },
            'WaitingForConfirmation': { icon: '⏱️', text: 'Confirmando...', class: 'info' },
            'ExecSuccess': { icon: '✅', text: 'Completado', class: 'success' },
            'ExecReverted': { icon: '❌', text: 'Revertido', class: 'error' },
            'Cancelled': { icon: '🚫', text: 'Cancelado', class: 'error' },
            'NotFound': { icon: '❓', text: 'No encontrado', class: 'warning' }
        };
        
        const config = statusConfig[status] || { icon: '❓', text: status, class: 'default' };
        
        return `
            <div class="gelato-status ${config.class}">
                <span class="status-icon">${config.icon}</span>
                <span class="status-text">${config.text}</span>
            </div>
        `;
    }
}

// Instancia global
const gelatoRelay = new GelatoRelay();

// Auto-inicializar si GELATO_RELAY_API_KEY está disponible
if (typeof GELATO_RELAY_API_KEY !== 'undefined' && GELATO_RELAY_API_KEY) {
    gelatoRelay.initialize(GELATO_RELAY_API_KEY)
        .then(() => console.log("✅ Gelato Relay auto-initialized"))
        .catch(err => console.error("❌ Auto-init failed:", err));
}

// Export para modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { GelatoRelay, gelatoRelay };
}
