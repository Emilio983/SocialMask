/**
 * Sistema de Pagos con Smart Wallet
 * Maneja todos los pagos usando smart accounts (NO Metamask)
 */

class SmartWalletPaymentSystem {
    constructor() {
        this.isProcessing = false;
        this.smartAccountAddress = null;
        this.balances = null;
    }

    /**
     * Inicializar sistema - obtener smart account del usuario
     */
    async init() {
        try {
            const response = await fetch('/api/wallet/balances.php', {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Error obteniendo datos de wallet');
            }

            const data = await response.json();
            
            if (data.success) {
                this.smartAccountAddress = data.smart_account_address;
                this.balances = data.balances;
                console.log('✅ Smart Wallet inicializada:', this.smartAccountAddress);
                return true;
            } else {
                throw new Error(data.message || 'Error inicializando wallet');
            }
        } catch (error) {
            console.error('❌ Error inicializando Smart Wallet:', error);
            return false;
        }
    }

    /**
     * Obtener balance actual de un token
     */
    async getBalance(token = 'SPHE') {
        try {
            const response = await fetch('/api/wallet/balances.php', {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Error obteniendo balance');
            }

            const data = await response.json();
            
            if (data.success && data.balances) {
                const tokenLower = token.toLowerCase();
                if (data.balances[tokenLower]) {
                    return parseFloat(data.balances[tokenLower].formatted) || 0;
                }
            }
            
            return 0;
        } catch (error) {
            console.error('Error obteniendo balance:', error);
            return 0;
        }
    }

    /**
     * Procesar pago de membership
     */
    async processMembershipPayment(plan, amount, token = 'SPHE') {
        if (this.isProcessing) {
            throw new Error('Ya hay un pago en proceso');
        }

        this.isProcessing = true;

        try {
            // Verificar balance
            const balance = await this.getBalance(token);
            if (balance < amount) {
                throw new Error(`Balance insuficiente. Necesitas ${amount} ${token} pero solo tienes ${balance.toFixed(2)} ${token}`);
            }

            // Confirmar con el usuario usando modal bonito
            const confirmed = await window.Toast.confirm(
                `Confirmarás el pago de ${amount} ${token} para membership ${plan.toUpperCase()}.\n\n` +
                `El pago se deducirá de tu Smart Wallet.\n` +
                `Balance actual: ${balance.toFixed(2)} ${token}\n` +
                `Balance después: ${(balance - amount).toFixed(2)} ${token}`,
                '💳 Confirmar Pago de Membership',
                {
                    confirmText: `Pagar ${amount} ${token}`,
                    cancelText: 'Cancelar',
                    type: 'info'
                }
            );

            if (!confirmed) {
                this.isProcessing = false;
                window.Toast.info('Pago cancelado');
                return { success: false, message: 'Pago cancelado por el usuario' };
            }

            // Procesar pago
            const response = await fetch('/api/payments/process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    type: 'MEMBERSHIP',
                    amount: amount,
                    token: token,
                    metadata: {
                        plan: plan
                    }
                })
            });

            const result = await response.json();

            if (result.success) {
                console.log('✅ Pago procesado:', result.data);
                return result;
            } else {
                throw new Error(result.message || 'Error procesando pago');
            }

        } catch (error) {
            console.error('❌ Error en pago:', error);
            throw error;
        } finally {
            this.isProcessing = false;
        }
    }

    /**
     * Procesar pago de creación de grupo
     */
    async processGroupCreationPayment(groupId, amount, token = 'SPHE') {
        if (this.isProcessing) {
            throw new Error('Ya hay un pago en proceso');
        }

        this.isProcessing = true;

        try {
            // Verificar balance
            const balance = await this.getBalance(token);
            if (balance < amount) {
                throw new Error(`Balance insuficiente. Necesitas ${amount} ${token} pero solo tienes ${balance.toFixed(2)} ${token}`);
            }

            // Confirmar con el usuario usando modal bonito
            const confirmed = await window.Toast.confirm(
                `Confirmarás el pago de ${amount} ${token} para crear el grupo.\n\n` +
                `El pago se deducirá de tu Smart Wallet.\n` +
                `Balance actual: ${balance.toFixed(2)} ${token}\n` +
                `Balance después: ${(balance - amount).toFixed(2)} ${token}`,
                '🏘️ Confirmar Creación de Grupo',
                {
                    confirmText: `Pagar ${amount} ${token}`,
                    cancelText: 'Cancelar',
                    type: 'info'
                }
            );

            if (!confirmed) {
                this.isProcessing = false;
                window.Toast.info('Creación cancelada');
                return { success: false, message: 'Pago cancelado por el usuario' };
            }

            // Procesar pago
            const response = await fetch('/api/payments/process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    type: 'GROUP_CREATION',
                    amount: amount,
                    token: token,
                    metadata: {
                        group_id: groupId
                    }
                })
            });

            const result = await response.json();

            if (result.success) {
                console.log('✅ Pago de grupo procesado:', result.data);
                return result;
            } else {
                throw new Error(result.message || 'Error procesando pago');
            }

        } catch (error) {
            console.error('❌ Error en pago de grupo:', error);
            throw error;
        } finally {
            this.isProcessing = false;
        }
    }

    /**
     * Procesar pago genérico
     */
    async processPayment(type, amount, token = 'SPHE', metadata = {}) {
        if (this.isProcessing) {
            throw new Error('Ya hay un pago en proceso');
        }

        this.isProcessing = true;

        try {
            // Verificar balance
            const balance = await this.getBalance(token);
            if (balance < amount) {
                throw new Error(`Balance insuficiente. Necesitas ${amount} ${token} pero solo tienes ${balance} ${token}`);
            }

            // Procesar pago
            const response = await fetch('/api/payments/process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    type: type,
                    amount: amount,
                    token: token,
                    metadata: metadata
                })
            });

            const result = await response.json();

            if (result.success) {
                console.log('✅ Pago procesado:', result.data);
                return result;
            } else {
                throw new Error(result.message || 'Error procesando pago');
            }

        } catch (error) {
            console.error('❌ Error en pago:', error);
            throw error;
        } finally {
            this.isProcessing = false;
        }
    }

    /**
     * Mostrar estado de carga durante el pago
     */
    showLoadingState(buttonElement, message = 'Procesando pago...') {
        if (buttonElement) {
            buttonElement.disabled = true;
            buttonElement.dataset.originalText = buttonElement.innerHTML;
            buttonElement.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                ${message}
            `;
        }
    }

    /**
     * Restaurar estado del botón
     */
    resetButtonState(buttonElement) {
        if (buttonElement && buttonElement.dataset.originalText) {
            buttonElement.disabled = false;
            buttonElement.innerHTML = buttonElement.dataset.originalText;
        }
    }

    /**
     * Mostrar error
     */
    showError(message) {
        if (window.Toast) {
            window.Toast.error(message);
        } else {
            alert('❌ Error: ' + message);
        }
    }

    /**
     * Mostrar éxito
     */
    showSuccess(message) {
        if (window.Toast) {
            window.Toast.success(message);
        } else {
            alert('✅ ' + message);
        }
    }
}

// Instancia global
window.SmartWalletPayments = new SmartWalletPaymentSystem();

// Inicializar automáticamente cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.SmartWalletPayments.init();
    });
} else {
    window.SmartWalletPayments.init();
}
