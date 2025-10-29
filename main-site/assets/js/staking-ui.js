/**
 * Staking UI
 * Maneja toda la interfaz de usuario del dashboard de staking
 */

class StakingUI {
    constructor(stakingManager) {
        this.manager = stakingManager;
        this.currentPool = 0;
        this.isLoading = false;
    }

    /**
     * Inicializar UI
     */
    async initialize() {
        try {
            this.setupEventListeners();
            await this.loadDashboard();
            await this.loadPools();
            await this.startRealtimeUpdates();
            
            console.log('Staking UI inicializada');
        } catch (error) {
            console.error('Error inicializando UI:', error);
            this.showError('Error inicializando dashboard');
        }
    }

    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Botones de acción
        document.getElementById('btn-stake')?.addEventListener('click', () => this.showStakeModal());
        document.getElementById('btn-unstake')?.addEventListener('click', () => this.showUnstakeModal());
        document.getElementById('btn-claim')?.addEventListener('click', () => this.handleClaim());
        
        // Modals
        document.getElementById('confirm-stake')?.addEventListener('click', () => this.handleStake());
        document.getElementById('confirm-unstake')?.addEventListener('click', () => this.handleUnstake());
        document.getElementById('confirm-approve')?.addEventListener('click', () => this.handleApprove());
        
        // Pool selector
        document.querySelectorAll('.pool-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const poolId = parseInt(e.currentTarget.dataset.poolId);
                this.selectPool(poolId);
            });
        });

        // Input validations
        document.getElementById('stake-amount')?.addEventListener('input', (e) => {
            this.validateStakeAmount(e.target.value);
        });

        document.getElementById('unstake-amount')?.addEventListener('input', (e) => {
            this.validateUnstakeAmount(e.target.value);
        });
    }

    /**
     * Cargar dashboard principal
     */
    async loadDashboard() {
        try {
            this.showLoading();

            // Obtener datos del smart contract
            const [stakeInfo, balance, tvl] = await Promise.all([
                this.manager.getStakeInfo(),
                this.manager.getTokenBalance(),
                this.manager.getTotalStaked()
            ]);

            // Obtener datos del backend
            const userId = await this.getUserId();
            const backendStats = await this.fetchBackendStats(userId);

            // Actualizar UI
            this.updateSummaryCards({
                currentStaked: stakeInfo.amount,
                totalRewards: backendStats.total_rewards_claimed || '0',
                pendingRewards: stakeInfo.pendingRewards,
                balance: balance,
                tvl: tvl
            });

            this.updateStakeDetails(stakeInfo);
            this.updatePoolIndicator(stakeInfo.poolId);

            this.hideLoading();
        } catch (error) {
            console.error('Error cargando dashboard:', error);
            this.showError('Error cargando datos del dashboard');
        }
    }

    /**
     * Cargar información de pools
     */
    async loadPools() {
        try {
            const poolsContainer = document.getElementById('pools-container');
            if (!poolsContainer) return;

            poolsContainer.innerHTML = '';

            // Cargar 4 pools
            for (let i = 0; i < 4; i++) {
                const poolInfo = await this.manager.getPoolInfo(i);
                const poolCard = this.createPoolCard(i, poolInfo);
                poolsContainer.appendChild(poolCard);
            }
        } catch (error) {
            console.error('Error cargando pools:', error);
        }
    }

    /**
     * Crear tarjeta de pool
     */
    createPoolCard(poolId, poolInfo) {
        const card = document.createElement('div');
        card.className = 'col-md-6 col-lg-3 mb-3';
        card.innerHTML = `
            <div class="card pool-card h-100 ${this.currentPool === poolId ? 'border-primary' : ''}" 
                 data-pool-id="${poolId}" 
                 style="cursor: pointer;">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-swimming-pool text-primary"></i>
                        ${poolInfo.name}
                    </h5>
                    <div class="pool-stats">
                        <div class="mb-2">
                            <small class="text-muted">APY</small>
                            <h3 class="text-success mb-0">${(poolInfo.apy / 100).toFixed(2)}%</h3>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Lock Period</small>
                            <div>${poolInfo.lockPeriod > 0 ? (poolInfo.lockPeriod / 86400) + ' días' : 'Flexible'}</div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Multiplier</small>
                            <div>${(poolInfo.rewardMultiplier / 100).toFixed(2)}x</div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">TVL</small>
                            <div>${parseFloat(poolInfo.totalPoolStaked).toFixed(2)} SPHE</div>
                        </div>
                        <div>
                            <small class="text-muted">Min Stake</small>
                            <div>${parseFloat(poolInfo.minStakeAmount).toFixed(2)} SPHE</div>
                        </div>
                    </div>
                    ${this.currentPool === poolId ? '<div class="badge bg-primary mt-2">Selected</div>' : ''}
                </div>
            </div>
        `;
        return card;
    }

    /**
     * Actualizar tarjetas resumen
     */
    updateSummaryCards(data) {
        this.updateElement('current-staked', parseFloat(data.currentStaked).toFixed(4) + ' SPHE');
        this.updateElement('total-rewards', parseFloat(data.totalRewards).toFixed(4) + ' SPHE');
        this.updateElement('pending-rewards', parseFloat(data.pendingRewards).toFixed(4) + ' SPHE');
        this.updateElement('wallet-balance', parseFloat(data.balance).toFixed(4) + ' SPHE');
        this.updateElement('total-tvl', parseFloat(data.tvl).toFixed(2) + ' SPHE');
    }

    /**
     * Actualizar detalles de stake
     */
    updateStakeDetails(stakeInfo) {
        const detailsContainer = document.getElementById('stake-details');
        if (!detailsContainer) return;

        const daysRemaining = this.manager.calculateDaysRemaining(stakeInfo.lockEndTime);
        const canUnstake = stakeInfo.canUnstake;

        detailsContainer.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Staked Since:</strong> ${this.manager.formatDate(stakeInfo.startTime)}</p>
                    <p><strong>Last Claim:</strong> ${this.manager.formatDate(stakeInfo.lastClaimTime)}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Lock Status:</strong> 
                        ${canUnstake 
                            ? '<span class="badge bg-success">Unlocked</span>' 
                            : `<span class="badge bg-warning">${daysRemaining} days remaining</span>`
                        }
                    </p>
                    <p><strong>Pool:</strong> Pool ${stakeInfo.poolId}</p>
                </div>
            </div>
        `;

        // Habilitar/deshabilitar botón unstake
        const unstakeBtn = document.getElementById('btn-unstake');
        if (unstakeBtn) {
            unstakeBtn.disabled = !canUnstake || parseFloat(stakeInfo.amount) === 0;
        }
    }

    /**
     * Iniciar actualizaciones en tiempo real
     */
    async startRealtimeUpdates() {
        // Actualizar rewards cada segundo
        this.manager.startRewardsCounter((rewards) => {
            this.updateElement('pending-rewards', parseFloat(rewards).toFixed(4) + ' SPHE');
        });

        // Actualizar stats cada 30 segundos
        this.manager.startStatsRefresh(async (stats) => {
            await this.loadDashboard();
        }, 30000);
    }

    /**
     * Mostrar modal de stake
     */
    async showStakeModal() {
        const modal = new bootstrap.Modal(document.getElementById('stakeModal'));
        
        // Resetear formulario
        document.getElementById('stake-amount').value = '';
        document.getElementById('stake-amount-error').textContent = '';
        
        // Actualizar balance disponible
        const balance = await this.manager.getTokenBalance();
        document.getElementById('available-balance').textContent = 
            `Available: ${parseFloat(balance).toFixed(4)} SPHE`;
        
        modal.show();
    }

    /**
     * Mostrar modal de unstake
     */
    async showUnstakeModal() {
        const modal = new bootstrap.Modal(document.getElementById('unstakeModal'));
        
        // Obtener cantidad stakeada
        const stakeInfo = await this.manager.getStakeInfo();
        
        document.getElementById('unstake-amount').value = '';
        document.getElementById('unstake-amount').max = parseFloat(stakeInfo.amount);
        document.getElementById('max-unstake').textContent = 
            `Staked: ${parseFloat(stakeInfo.amount).toFixed(4)} SPHE`;
        
        modal.show();
    }

    /**
     * Handle approve tokens
     */
    async handleApprove() {
        try {
            const amount = document.getElementById('stake-amount').value;
            if (!amount || parseFloat(amount) <= 0) {
                throw new Error('Enter a valid amount');
            }

            this.showLoading('Approving tokens...');
            
            const txHash = await this.manager.approveTokens(amount);
            
            this.showSuccess(`Tokens approved! TX: ${this.shortenHash(txHash)}`);
            
            // Habilitar botón de stake
            document.getElementById('confirm-stake').disabled = false;
            
        } catch (error) {
            console.error('Error approving:', error);
            this.showError(error.message || 'Error approving tokens');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Handle stake
     */
    async handleStake() {
        try {
            const amount = document.getElementById('stake-amount').value;
            if (!amount || parseFloat(amount) <= 0) {
                throw new Error('Enter a valid amount');
            }

            this.showLoading('Staking tokens...');
            
            const txHash = await this.manager.stake(amount, this.currentPool);
            
            // Registrar en backend
            await this.registerStakeInBackend(amount, this.currentPool, txHash);
            
            this.showSuccess(`Stake successful! TX: ${this.shortenHash(txHash)}`);
            
            // Cerrar modal y recargar
            bootstrap.Modal.getInstance(document.getElementById('stakeModal')).hide();
            await this.loadDashboard();
            
        } catch (error) {
            console.error('Error staking:', error);
            this.showError(error.message || 'Error staking tokens');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Handle unstake
     */
    async handleUnstake() {
        try {
            const amount = document.getElementById('unstake-amount').value;
            const unstakeAmount = amount || 0; // 0 = unstake all

            this.showLoading('Unstaking tokens...');
            
            const txHash = await this.manager.unstake(unstakeAmount);
            
            // Registrar en backend
            await this.registerUnstakeInBackend(unstakeAmount, txHash);
            
            this.showSuccess(`Unstake successful! TX: ${this.shortenHash(txHash)}`);
            
            // Cerrar modal y recargar
            bootstrap.Modal.getInstance(document.getElementById('unstakeModal')).hide();
            await this.loadDashboard();
            
        } catch (error) {
            console.error('Error unstaking:', error);
            this.showError(error.message || 'Error unstaking tokens');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Handle claim rewards
     */
    async handleClaim() {
        try {
            const stakeInfo = await this.manager.getStakeInfo();
            if (parseFloat(stakeInfo.pendingRewards) === 0) {
                throw new Error('No rewards to claim');
            }

            if (!confirm(`Claim ${parseFloat(stakeInfo.pendingRewards).toFixed(4)} SPHE?`)) {
                return;
            }

            this.showLoading('Claiming rewards...');
            
            const txHash = await this.manager.claimRewards();
            
            // Registrar en backend
            await this.registerClaimInBackend(stakeInfo.pendingRewards, txHash);
            
            this.showSuccess(`Rewards claimed! TX: ${this.shortenHash(txHash)}`);
            
            await this.loadDashboard();
            
        } catch (error) {
            console.error('Error claiming:', error);
            this.showError(error.message || 'Error claiming rewards');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Seleccionar pool
     */
    selectPool(poolId) {
        this.currentPool = poolId;
        
        // Actualizar UI
        document.querySelectorAll('.pool-card').forEach(card => {
            card.classList.remove('border-primary');
            const badge = card.querySelector('.badge');
            if (badge) badge.remove();
        });
        
        const selectedCard = document.querySelector(`[data-pool-id="${poolId}"]`);
        if (selectedCard) {
            selectedCard.classList.add('border-primary');
            selectedCard.querySelector('.card-body').insertAdjacentHTML(
                'beforeend',
                '<div class="badge bg-primary mt-2">Selected</div>'
            );
        }
        
        console.log('Pool selected:', poolId);
    }

    /**
     * Validar monto de stake
     */
    async validateStakeAmount(amount) {
        const errorElement = document.getElementById('stake-amount-error');
        const approveBtn = document.getElementById('confirm-approve');
        const stakeBtn = document.getElementById('confirm-stake');

        try {
            const balance = await this.manager.getTokenBalance();
            const poolInfo = await this.manager.getPoolInfo(this.currentPool);
            const minStake = parseFloat(poolInfo.minStakeAmount);

            if (!amount || parseFloat(amount) <= 0) {
                throw new Error('Enter an amount');
            }

            if (parseFloat(amount) > parseFloat(balance)) {
                throw new Error('Insufficient balance');
            }

            if (parseFloat(amount) < minStake) {
                throw new Error(`Minimum stake: ${minStake} SPHE`);
            }

            errorElement.textContent = '';
            errorElement.className = 'text-success';
            errorElement.textContent = '✓ Valid amount';
            approveBtn.disabled = false;

        } catch (error) {
            errorElement.textContent = error.message;
            errorElement.className = 'text-danger';
            approveBtn.disabled = true;
            stakeBtn.disabled = true;
        }
    }

    /**
     * Validar monto de unstake
     */
    async validateUnstakeAmount(amount) {
        const errorElement = document.getElementById('unstake-amount-error');
        const confirmBtn = document.getElementById('confirm-unstake');

        try {
            const stakeInfo = await this.manager.getStakeInfo();
            const staked = parseFloat(stakeInfo.amount);

            if (amount && parseFloat(amount) > staked) {
                throw new Error('Amount exceeds staked balance');
            }

            errorElement.textContent = '';
            confirmBtn.disabled = false;

        } catch (error) {
            errorElement.textContent = error.message;
            errorElement.className = 'text-danger';
            confirmBtn.disabled = true;
        }
    }

    /**
     * Registrar stake en backend
     */
    async registerStakeInBackend(amount, poolId, txHash) {
        try {
            const userId = await this.getUserId();
            
            const response = await fetch('/api/staking/stake_tokens.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    amount: amount,
                    pool_id: poolId,
                    tx_hash: txHash
                })
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message);
            }

            console.log('Stake registered in backend');
        } catch (error) {
            console.error('Error registering stake:', error);
            // No throw - el stake en blockchain ya se hizo
        }
    }

    /**
     * Registrar unstake en backend
     */
    async registerUnstakeInBackend(amount, txHash) {
        try {
            const userId = await this.getUserId();
            
            const response = await fetch('/api/staking/unstake_tokens.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    amount: amount || '0',
                    tx_hash: txHash
                })
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message);
            }

            console.log('Unstake registered in backend');
        } catch (error) {
            console.error('Error registering unstake:', error);
        }
    }

    /**
     * Registrar claim en backend
     */
    async registerClaimInBackend(amount, txHash) {
        try {
            const userId = await this.getUserId();
            
            const response = await fetch('/api/staking/claim_rewards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    amount: amount,
                    tx_hash: txHash
                })
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message);
            }

            console.log('Claim registered in backend');
        } catch (error) {
            console.error('Error registering claim:', error);
        }
    }

    /**
     * Obtener stats del backend
     */
    async fetchBackendStats(userId) {
        try {
            const response = await fetch(`/api/staking/get_staking_info.php?user_id=${userId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message);
            }

            return data.data.summary;
        } catch (error) {
            console.error('Error fetching backend stats:', error);
            return {};
        }
    }

    /**
     * Obtener user ID (desde sesión o localStorage)
     */
    async getUserId() {
        const userId = sessionStorage.getItem('user_id');
        
        if (!userId) {
            // Intentar obtener de localStorage como fallback
            const localUserId = localStorage.getItem('user_id');
            if (localUserId) {
                sessionStorage.setItem('user_id', localUserId);
                return parseInt(localUserId);
            }
            
            throw new Error('User not authenticated. Please login.');
        }
        
        return parseInt(userId);
    }

    /**
     * Utilidades UI
     */
    updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    }

    showLoading(message = 'Loading...') {
        this.isLoading = true;
        const loader = document.getElementById('loading-overlay');
        if (loader) {
            loader.style.display = 'flex';
            loader.querySelector('.loading-text').textContent = message;
        }
    }

    hideLoading() {
        this.isLoading = false;
        const loader = document.getElementById('loading-overlay');
        if (loader) loader.style.display = 'none';
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'danger');
    }

    showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast"></button>
            </div>
        `;

        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    updatePoolIndicator(poolId) {
        this.selectPool(poolId);
    }

    shortenHash(hash) {
        if (!hash) return '';
        return hash.substring(0, 10) + '...' + hash.substring(hash.length - 8);
    }

    /**
     * Cleanup
     */
    cleanup() {
        this.manager.cleanup();
    }
}

// Exportar
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StakingUI;
}
