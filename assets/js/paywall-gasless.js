/**
 * PayPerView con Gelato Relay - Gasless Transactions
 * Permite comprar contenido sin pagar gas
 */

class PayPerViewGasless {
    constructor() {
        this.contract = null;
        this.spheContract = null;
        this.initialized = false;
    }
    
    /**
     * Inicializar contratos
     */
    async initialize() {
        if (!window.smartWalletProvider) {
            throw new Error("Smart Wallet no detectada");
        }
        
        if (!window.ethers) {
            throw new Error("Ethers.js no cargado");
        }
        
        if (!window.gelatoRelay || !gelatoRelay.isAvailable()) {
            throw new Error("Gelato Relay no disponible");
        }
        
        const provider = new ethers.BrowserProvider(window.smartWalletProvider);
        const signer = await provider.getSigner();
        
        // Cargar contratos
        this.contract = new ethers.Contract(
            PAYPERVIEW_CONTRACT_ADDRESS,
            PAYPERVIEW_ABI,
            signer
        );
        
        this.spheContract = new ethers.Contract(
            SPHE_CONTRACT_ADDRESS,
            SPHE_ABI,
            signer
        );
        
        this.initialized = true;
        console.log("✅ PayPerViewGasless initialized");
    }
    
    /**
     * Comprar contenido con gasless transaction
     * @param {number} contentId - ID del contenido
     * @param {function} onStatusUpdate - Callback para updates
     * @returns {Promise<object>}
     */
    async purchaseContentGasless(contentId, onStatusUpdate = null) {
        if (!this.initialized) {
            await this.initialize();
        }
        
        try {
            // 1. Obtener precio del contenido
            console.log(`📋 Obteniendo info del contenido ${contentId}...`);
            const [creator, price, active, totalSales, totalRevenue] = 
                await this.contract.getContentInfo(contentId);
            
            if (!active) {
                throw new Error("Contenido no está activo");
            }
            
            console.log(`💰 Precio: ${ethers.formatEther(price)} SPHE`);
            
            // 2. Verificar si ya tiene acceso
            const userAddress = await gelatoRelay.getUserAddress();
            const hasAccess = await this.contract.hasContentAccess(contentId, userAddress);
            
            if (hasAccess) {
                throw new Error("Ya tienes acceso a este contenido");
            }
            
            // 3. Verificar balance de SPHE
            const balance = await this.spheContract.balanceOf(userAddress);
            console.log(`💵 Tu balance: ${ethers.formatEther(balance)} SPHE`);
            
            if (balance < price) {
                throw new Error(`Balance insuficiente. Necesitas ${ethers.formatEther(price)} SPHE`);
            }
            
            // 4. Aprobar tokens (esta transacción SÍ requiere gas)
            console.log("🔓 Aprobando SPHE tokens...");
            
            if (onStatusUpdate) {
                onStatusUpdate({
                    step: 1,
                    total: 3,
                    status: 'Aprobando tokens (requiere firma)...',
                    taskState: 'Pending'
                });
            }
            
            const allowance = await this.spheContract.allowance(
                userAddress, 
                PAYPERVIEW_CONTRACT_ADDRESS
            );
            
            if (allowance < price) {
                const approveTx = await this.spheContract.approve(
                    PAYPERVIEW_CONTRACT_ADDRESS,
                    price
                );
                
                console.log(`⏳ Esperando confirmación de approve: ${approveTx.hash}`);
                await approveTx.wait();
                console.log("✅ Tokens aprobados");
            } else {
                console.log("✅ Tokens ya aprobados");
            }
            
            // 5. Preparar datos para gasless purchase
            console.log("📦 Preparando transacción gasless...");
            
            if (onStatusUpdate) {
                onStatusUpdate({
                    step: 2,
                    total: 3,
                    status: 'Comprando contenido (sin gas)...',
                    taskState: 'Preparing'
                });
            }
            
            const iface = new ethers.Interface(PAYPERVIEW_ABI);
            const data = iface.encodeFunctionData("purchaseContent", [contentId]);
            
            // 6. Estimar gas
            const estimatedGas = await gelatoRelay.estimateGas(
                PAYPERVIEW_CONTRACT_ADDRESS,
                data
            );
            
            console.log(`⛽ Gas estimado: ${estimatedGas}`);
            
            // 7. Enviar transacción gasless con Gelato
            const taskId = await gelatoRelay.sponsoredCall(
                PAYPERVIEW_CONTRACT_ADDRESS,
                data,
                estimatedGas.toString()
            );
            
            console.log(`📤 Transacción enviada. Task ID: ${taskId}`);
            
            if (onStatusUpdate) {
                onStatusUpdate({
                    step: 2,
                    total: 3,
                    status: 'Esperando confirmación...',
                    taskState: 'ExecPending',
                    taskId: taskId
                });
            }
            
            // 8. Esperar confirmación
            const result = await gelatoRelay.waitForTask(
                taskId,
                60000, // 60 segundos
                (status) => {
                    if (onStatusUpdate) {
                        onStatusUpdate({
                            step: 2,
                            total: 3,
                            status: `Estado: ${status.taskState}`,
                            taskState: status.taskState,
                            taskId: taskId
                        });
                    }
                }
            );
            
            console.log(`✅ Transacción confirmada: ${result.transactionHash}`);
            
            // 9. Registrar en backend
            if (onStatusUpdate) {
                onStatusUpdate({
                    step: 3,
                    total: 3,
                    status: 'Registrando compra...',
                    taskState: 'Recording'
                });
            }
            
            await this.recordPurchase(contentId, result.transactionHash, taskId);
            
            console.log("✅ Compra completada exitosamente!");
            
            return {
                success: true,
                contentId: contentId,
                txHash: result.transactionHash,
                taskId: taskId,
                price: ethers.formatEther(price),
                message: "Contenido desbloqueado exitosamente (sin gas)"
            };
            
        } catch (error) {
            console.error("❌ Error en compra gasless:", error);
            
            if (onStatusUpdate) {
                onStatusUpdate({
                    step: 0,
                    total: 3,
                    status: `Error: ${error.message}`,
                    taskState: 'Error'
                });
            }
            
            throw error;
        }
    }
    
    /**
     * Comprar contenido con transacción normal (con gas)
     * @param {number} contentId - ID del contenido
     * @returns {Promise<object>}
     */
    async purchaseContentNormal(contentId) {
        if (!this.initialized) {
            await this.initialize();
        }
        
        try {
            // Obtener precio
            const [creator, price, active] = await this.contract.getContentInfo(contentId);
            
            if (!active) {
                throw new Error("Contenido no está activo");
            }
            
            const userAddress = await gelatoRelay.getUserAddress();
            
            // Aprobar tokens
            const allowance = await this.spheContract.allowance(
                userAddress,
                PAYPERVIEW_CONTRACT_ADDRESS
            );
            
            if (allowance < price) {
                const approveTx = await this.spheContract.approve(
                    PAYPERVIEW_CONTRACT_ADDRESS,
                    price
                );
                await approveTx.wait();
            }
            
            // Comprar
            const purchaseTx = await this.contract.purchaseContent(contentId);
            const receipt = await purchaseTx.wait();
            
            // Registrar en backend
            await this.recordPurchase(contentId, receipt.hash, null);
            
            return {
                success: true,
                contentId: contentId,
                txHash: receipt.hash,
                price: ethers.formatEther(price)
            };
            
        } catch (error) {
            console.error("❌ Error en compra normal:", error);
            throw error;
        }
    }
    
    /**
     * Registrar compra en backend
     * @param {number} contentId 
     * @param {string} txHash 
     * @param {string} taskId 
     */
    async recordPurchase(contentId, txHash, taskId) {
        try {
            const response = await fetch('/api/paywall/record_purchase.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${getJWT()}`
                },
                body: JSON.stringify({
                    content_id: contentId,
                    tx_hash: txHash,
                    gelato_task_id: taskId
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log("✅ Compra registrada en backend:", result);
            
        } catch (error) {
            console.error("⚠️ Error registrando compra en backend:", error);
            // No lanzar error - la compra on-chain fue exitosa
        }
    }
    
    /**
     * Verificar acceso a contenido
     * @param {number} contentId 
     * @returns {Promise<boolean>}
     */
    async hasAccess(contentId) {
        if (!this.initialized) {
            await this.initialize();
        }
        
        const userAddress = await gelatoRelay.getUserAddress();
        return await this.contract.hasContentAccess(contentId, userAddress);
    }
    
    /**
     * Obtener balance de SPHE del usuario
     * @returns {Promise<string>}
     */
    async getUserBalance() {
        if (!this.initialized) {
            await this.initialize();
        }
        
        const userAddress = await gelatoRelay.getUserAddress();
        const balance = await this.spheContract.balanceOf(userAddress);
        return ethers.formatEther(balance);
    }
    
    /**
     * Obtener información de contenido
     * @param {number} contentId 
     * @returns {Promise<object>}
     */
    async getContentInfo(contentId) {
        if (!this.initialized) {
            await this.initialize();
        }
        
        const [creator, price, active, totalSales, totalRevenue] = 
            await this.contract.getContentInfo(contentId);
        
        return {
            creator: creator,
            price: ethers.formatEther(price),
            priceWei: price.toString(),
            active: active,
            totalSales: totalSales.toString(),
            totalRevenue: ethers.formatEther(totalRevenue)
        };
    }
}

// Instancia global
const payPerViewGasless = new PayPerViewGasless();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PayPerViewGasless, payPerViewGasless };
}
