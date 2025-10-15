/**
 * Staking Manager
 * Gestiona todas las interacciones con el smart contract de staking
 */

class StakingManager {
    constructor() {
        this.contractAddress = null;
        this.contractABI = null;
        this.contract = null;
        this.web3 = null;
        this.userAddress = null;
        this.tokenContract = null;
        this.tokenAddress = null;
        
        this.rewardsCounter = null;
        this.statsRefreshInterval = null;
    }

    /**
     * Inicializar el manager
     */
    async initialize(contractAddress, contractABI, tokenAddress, tokenABI) {
        try {
            this.contractAddress = contractAddress;
            this.contractABI = contractABI;
            this.tokenAddress = tokenAddress;

            // Verificar Web3
            if (typeof window.smartWalletProvider === 'undefined') {
                throw new Error('Smart Wallet no está disponible');
            }

            this.web3 = new Web3(window.smartWalletProvider);

            // Solicitar cuentas
            const accounts = await window.smartWalletProvider.request({ 
                method: 'eth_requestAccounts' 
            });
            this.userAddress = accounts[0];

            // Inicializar contratos
            this.contract = new this.web3.eth.Contract(
                this.contractABI,
                this.contractAddress
            );

            this.tokenContract = new this.web3.eth.Contract(
                tokenABI,
                this.tokenAddress
            );

            // Escuchar cambios de cuenta
            this.setupAccountListener();

            console.log('Staking Manager inicializado');
            console.log('Contract:', this.contractAddress);
            console.log('User:', this.userAddress);

            return true;
        } catch (error) {
            console.error('Error inicializando Staking Manager:', error);
            throw error;
        }
    }

    /**
     * Configurar listener para cambios de cuenta en Smart Wallet
     */
    setupAccountListener() {
        if (window.smartWalletProvider) {
            window.smartWalletProvider.on('accountsChanged', (accounts) => {
                console.log('Account changed:', accounts[0]);
                this.userAddress = accounts[0];
                
                // Recargar datos si hay un callback
                if (this.onAccountChanged) {
                    this.onAccountChanged(accounts[0]);
                }
                
                // Forzar recarga de página como fallback
                window.location.reload();
            });
        }
    }

    /**
     * Obtener información de stake del usuario
     */
    async getStakeInfo() {
        try {
            const info = await this.contract.methods
                .getStakeInfo(this.userAddress)
                .call();

            return {
                amount: this.web3.utils.fromWei(info.amount, 'ether'),
                startTime: parseInt(info.startTime),
                lastClaimTime: parseInt(info.lastClaimTime),
                accumulatedRewards: this.web3.utils.fromWei(info.accumulatedRewards, 'ether'),
                pendingRewards: this.web3.utils.fromWei(info.pendingRewards, 'ether'),
                poolId: parseInt(info.poolId),
                lockEndTime: parseInt(info.lockEndTime),
                canUnstake: info.canUnstake
            };
        } catch (error) {
            console.error('Error obteniendo stake info:', error);
            throw error;
        }
    }

    /**
     * Obtener información de un pool
     */
    async getPoolInfo(poolId) {
        try {
            const info = await this.contract.methods
                .getPoolInfo(poolId)
                .call();

            return {
                name: info.name,
                lockPeriod: parseInt(info.lockPeriod),
                rewardMultiplier: parseInt(info.rewardMultiplier),
                totalPoolStaked: this.web3.utils.fromWei(info.totalPoolStaked, 'ether'),
                minStakeAmount: this.web3.utils.fromWei(info.minStakeAmount, 'ether'),
                active: info.active,
                apy: parseInt(info.apy)
            };
        } catch (error) {
            console.error('Error obteniendo pool info:', error);
            throw error;
        }
    }

    /**
     * Obtener balance de tokens del usuario
     */
    async getTokenBalance() {
        try {
            const balance = await this.tokenContract.methods
                .balanceOf(this.userAddress)
                .call();

            return this.web3.utils.fromWei(balance, 'ether');
        } catch (error) {
            console.error('Error obteniendo balance:', error);
            throw error;
        }
    }

    /**
     * Aprobar tokens para staking
     */
    async approveTokens(amount) {
        try {
            const amountWei = this.web3.utils.toWei(amount.toString(), 'ether');

            const tx = await this.tokenContract.methods
                .approve(this.contractAddress, amountWei)
                .send({ from: this.userAddress });

            console.log('Aprobación exitosa:', tx.transactionHash);
            return tx.transactionHash;
        } catch (error) {
            console.error('Error aprobando tokens:', error);
            throw error;
        }
    }

    /**
     * Stakear tokens
     */
    async stake(amount, poolId) {
        try {
            const amountWei = this.web3.utils.toWei(amount.toString(), 'ether');

            // Verificar aprobación primero
            const allowance = await this.tokenContract.methods
                .allowance(this.userAddress, this.contractAddress)
                .call();

            if (this.web3.utils.toBN(allowance).lt(this.web3.utils.toBN(amountWei))) {
                throw new Error('Debes aprobar los tokens primero');
            }

            // Stakear
            const tx = await this.contract.methods
                .stake(amountWei, poolId)
                .send({ from: this.userAddress });

            console.log('Stake exitoso:', tx.transactionHash);
            return tx.transactionHash;
        } catch (error) {
            console.error('Error stakeando:', error);
            throw error;
        }
    }

    /**
     * Unstakear tokens
     */
    async unstake(amount) {
        try {
            const amountWei = amount === 0 
                ? '0' 
                : this.web3.utils.toWei(amount.toString(), 'ether');

            const tx = await this.contract.methods
                .unstake(amountWei)
                .send({ from: this.userAddress });

            console.log('Unstake exitoso:', tx.transactionHash);
            return tx.transactionHash;
        } catch (error) {
            console.error('Error unstakeando:', error);
            throw error;
        }
    }

    /**
     * Reclamar rewards
     */
    async claimRewards() {
        try {
            const tx = await this.contract.methods
                .claimRewards()
                .send({ from: this.userAddress });

            console.log('Claim exitoso:', tx.transactionHash);
            return tx.transactionHash;
        } catch (error) {
            console.error('Error reclamando rewards:', error);
            throw error;
        }
    }

    /**
     * Emergency withdraw
     */
    async emergencyWithdraw() {
        try {
            const tx = await this.contract.methods
                .emergencyWithdraw()
                .send({ from: this.userAddress });

            console.log('Emergency withdraw exitoso:', tx.transactionHash);
            return tx.transactionHash;
        } catch (error) {
            console.error('Error en emergency withdraw:', error);
            throw error;
        }
    }

    /**
     * Calcular rewards pendientes en tiempo real
     */
    async calculateRewards() {
        try {
            const rewards = await this.contract.methods
                .calculateRewards(this.userAddress)
                .call();

            return this.web3.utils.fromWei(rewards, 'ether');
        } catch (error) {
            console.error('Error calculando rewards:', error);
            return '0';
        }
    }

    /**
     * Obtener TVL total
     */
    async getTotalStaked() {
        try {
            const total = await this.contract.methods
                .totalStaked()
                .call();

            return this.web3.utils.fromWei(total, 'ether');
        } catch (error) {
            console.error('Error obteniendo TVL:', error);
            return '0';
        }
    }

    /**
     * Calcular APY de un pool
     */
    async calculateAPY(poolId) {
        try {
            const apy = await this.contract.methods
                .calculateAPY(poolId)
                .call();

            // APY viene en basis points (10000 = 100%)
            return (parseInt(apy) / 100).toFixed(2);
        } catch (error) {
            console.error('Error calculando APY:', error);
            return '0.00';
        }
    }

    /**
     * Iniciar contador de rewards en tiempo real
     */
    startRewardsCounter(updateCallback) {
        if (this.rewardsCounter) {
            clearInterval(this.rewardsCounter);
        }

        this.rewardsCounter = setInterval(async () => {
            try {
                const rewards = await this.calculateRewards();
                updateCallback(rewards);
            } catch (error) {
                console.error('Error actualizando rewards:', error);
            }
        }, 1000); // Actualizar cada segundo
    }

    /**
     * Detener contador de rewards
     */
    stopRewardsCounter() {
        if (this.rewardsCounter) {
            clearInterval(this.rewardsCounter);
            this.rewardsCounter = null;
        }
    }

    /**
     * Iniciar actualización automática de estadísticas
     */
    startStatsRefresh(updateCallback, interval = 30000) {
        if (this.statsRefreshInterval) {
            clearInterval(this.statsRefreshInterval);
        }

        this.statsRefreshInterval = setInterval(async () => {
            try {
                const stats = await this.getStakeInfo();
                updateCallback(stats);
            } catch (error) {
                console.error('Error actualizando stats:', error);
            }
        }, interval);
    }

    /**
     * Detener actualización de estadísticas
     */
    stopStatsRefresh() {
        if (this.statsRefreshInterval) {
            clearInterval(this.statsRefreshInterval);
            this.statsRefreshInterval = null;
        }
    }

    /**
     * Formatear número con decimales
     */
    formatNumber(number, decimals = 4) {
        return parseFloat(number).toFixed(decimals);
    }

    /**
     * Formatear timestamp a fecha
     */
    formatDate(timestamp) {
        if (!timestamp || timestamp === 0) return '-';
        const date = new Date(timestamp * 1000);
        return date.toLocaleString();
    }

    /**
     * Calcular días restantes de lock
     */
    calculateDaysRemaining(lockEndTime) {
        if (!lockEndTime || lockEndTime === 0) return 0;
        
        const now = Math.floor(Date.now() / 1000);
        const remaining = lockEndTime - now;
        
        if (remaining <= 0) return 0;
        
        return Math.ceil(remaining / 86400); // Convertir a días
    }

    /**
     * Obtener eventos del contrato
     */
    async getPastEvents(eventName, fromBlock = 0) {
        try {
            const events = await this.contract.getPastEvents(eventName, {
                filter: { user: this.userAddress },
                fromBlock: fromBlock,
                toBlock: 'latest'
            });

            return events.map(event => ({
                ...event.returnValues,
                blockNumber: event.blockNumber,
                transactionHash: event.transactionHash
            }));
        } catch (error) {
            console.error(`Error obteniendo eventos ${eventName}:`, error);
            return [];
        }
    }

    /**
     * Escuchar eventos en tiempo real
     */
    listenToEvents(eventName, callback) {
        this.contract.events[eventName]({
            filter: { user: this.userAddress }
        })
        .on('data', (event) => {
            callback(event.returnValues);
        })
        .on('error', (error) => {
            console.error(`Error en evento ${eventName}:`, error);
        });
    }

    /**
     * Cleanup - limpiar intervalos y listeners
     */
    cleanup() {
        this.stopRewardsCounter();
        this.stopStatsRefresh();
        console.log('Staking Manager limpiado');
    }
}

// Exportar para uso global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StakingManager;
}
