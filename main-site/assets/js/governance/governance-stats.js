/**
 * ============================================
 * GOVERNANCE STATS MODULE
 * ============================================
 * Handles stats display and voting power widget
 */

class GovernanceStats {
    constructor() {
        this.statsContainer = document.getElementById('governanceStats');
        this.votingPowerContainer = document.getElementById('votingPowerWidget');
    }
    
    /**
     * Load and display system statistics
     */
    async loadStats() {
        try {
            const stats = await window.GovernanceAPI.getStats();
            this.renderStats(stats);
        } catch (error) {
            console.error('Error loading stats:', error);
            this.showStatsError();
        }
    }
    
    /**
     * Render statistics cards
     */
    renderStats(stats) {
        if (!this.statsContainer) return;
        
        this.statsContainer.innerHTML = `
            <!-- Total Proposals -->
            <div class="bg-brand-bg-secondary rounded-lg shadow p-6 fade-in hover:shadow-lg transition">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-brand-text-secondary600">Total Proposals</h3>
                    <i class="fas fa-file-alt text-blue-500 text-xl"></i>
                </div>
                <p class="text-3xl font-bold text-brand-text-secondary900">${stats.total_proposals || 0}</p>
                <div class="mt-2 text-sm text-brand-text-secondary500">
                    <span class="text-green-600 font-semibold">${stats.proposals_by_status?.active || 0}</span> active
                </div>
            </div>
            
            <!-- Total Voters -->
            <div class="bg-brand-bg-secondary rounded-lg shadow p-6 fade-in hover:shadow-lg transition">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-brand-text-secondary600">Total Voters</h3>
                    <i class="fas fa-users text-purple-500 text-xl"></i>
                </div>
                <p class="text-3xl font-bold text-brand-text-secondary900">${stats.total_voters || 0}</p>
                <div class="mt-2 text-sm text-brand-text-secondary500">
                    Unique participants
                </div>
            </div>
            
            <!-- Total Votes -->
            <div class="bg-brand-bg-secondary rounded-lg shadow p-6 fade-in hover:shadow-lg transition">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-brand-text-secondary600">Total Votes</h3>
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <p class="text-3xl font-bold text-brand-text-secondary900">${stats.total_votes || 0}</p>
                <div class="mt-2 text-sm text-brand-text-secondary500">
                    Votes cast
                </div>
            </div>
            
            <!-- Participation Rate -->
            <div class="bg-brand-bg-secondary rounded-lg shadow p-6 fade-in hover:shadow-lg transition">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-brand-text-secondary600">Participation</h3>
                    <i class="fas fa-chart-line text-orange-500 text-xl"></i>
                </div>
                <p class="text-3xl font-bold text-brand-text-secondary900">${stats.participation_rate || 0}%</p>
                <div class="mt-2 text-sm text-brand-text-secondary500">
                    Average participation
                </div>
            </div>
        `;
    }
    
    /**
     * Show error state for stats
     */
    showStatsError() {
        if (!this.statsContainer) return;
        
        this.statsContainer.innerHTML = `
            <div class="col-span-full bg-brand-bg-secondary border border-brand-border rounded-lg p-4 text-center">
                <i class="fas fa-exclamation-triangle text-brand-accent text-2xl mb-2"></i>
                <p class="text-brand-text-secondary">Error al cargar estadísticas</p>
                <button onclick="window.GovernanceStats?.loadStats()" class="mt-2 text-brand-accent hover:underline text-sm">
                    Reintentar
                </button>
            </div>
        `;
    }
    
    /**
     * Load and display voting power widget
     */
    async loadVotingPower(wallet) {
        if (!wallet || !this.votingPowerContainer) return;
        
        try {
            const data = await window.GovernanceAPI.getVotingPower(wallet);
            this.renderVotingPower(data);
        } catch (error) {
            console.error('Error loading voting power:', error);
            this.showVotingPowerError();
        }
    }
    
    /**
     * Render voting power widget
     */
    renderVotingPower(data) {
        if (!this.votingPowerContainer) return;
        
        const canVote = data.capabilities?.can_vote || false;
        const canPropose = data.capabilities?.can_propose || false;
        const isDelegated = data.delegation?.is_delegated || false;
        
        this.votingPowerContainer.innerHTML = `
            <div class="bg-gradient-to-r from-purple-500 to-indigo-600 rounded-lg shadow-lg p-6 text-brand-text-primary fade-in">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold mb-2">
                            <i class="fas fa-bolt mr-2"></i>
                            Your Voting Power
                        </h3>
                        <p class="text-4xl font-bold mb-2">${data.voting_power?.formatted || '0 GOVSPHE'}</p>
                        <p class="text-purple-100 text-sm">
                            Token Balance: ${data.token_balance?.formatted || '0 GOVSPHE'}
                        </p>
                    </div>
                    
                    <div class="space-y-3">
                        ${isDelegated ? `
                            <div class="bg-brand-bg-secondary bg-opacity-20 rounded-lg p-3">
                                <p class="text-sm mb-1">Delegated to:</p>
                                <p class="font-mono text-xs">${this.truncateAddress(data.delegation.delegated_to)}</p>
                                <button onclick="window.GovernanceModals.openDelegateModal()" class="mt-2 text-xs underline hover:text-purple-200">
                                    Change delegation
                                </button>
                            </div>
                        ` : `
                            <button onclick="window.GovernanceModals.openDelegateModal()" class="bg-brand-bg-secondary text-purple-600 px-4 py-2 rounded-lg font-semibold hover:bg-purple-50 transition">
                                <i class="fas fa-hand-holding-heart mr-2"></i>
                                Delegate Power
                            </button>
                        `}
                        
                        <div class="flex gap-2 text-sm">
                            <span class="px-3 py-1 rounded-full ${canVote ? 'bg-green-400 text-green-900' : 'bg-brand-bg-primary text-brand-text-secondary'}">
                                <i class="fas fa-check mr-1"></i>
                                ${canVote ? 'Can Vote' : 'Cannot Vote'}
                            </span>
                            <span class="px-3 py-1 rounded-full ${canPropose ? 'bg-green-400 text-green-900' : 'bg-brand-bg-primary text-brand-text-secondary'}">
                                <i class="fas fa-plus mr-1"></i>
                                ${canPropose ? 'Can Propose' : 'Need 1000 GOV'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Show error for voting power widget
     */
    showVotingPowerError() {
        if (!this.votingPowerContainer) return;
        
        this.votingPowerContainer.innerHTML = `
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-4 text-center">
                <i class="fas fa-exclamation-triangle text-brand-accent text-xl mb-2"></i>
                <p class="text-brand-text-secondary">Error al cargar poder de voto</p>
                <button onclick="window.GovernanceStats?.loadVotingPower(window.__SPHERA_GOVERNANCE__?.userWallet)" class="mt-2 text-brand-accent hover:underline text-sm">
                    Reintentar
                </button>
            </div>
        `;
    }
    
    /**
     * Update voting power with Web3 on-chain data
     * @param {Object} web3Data - On-chain data from connected wallet
     */
    updateWeb3Data(web3Data) {
        if (!this.votingPowerContainer) return;
        
        const { address, chainId, balance, votingPower, delegate } = web3Data;
        
        // Add Web3 sync indicator
        this.addWeb3SyncIndicator(true, chainId, balance, votingPower);
    }
    
    /**
     * Clear Web3 data when wallet disconnects
     */
    clearWeb3Data() {
        // Remove sync indicator if exists
        this.addWeb3SyncIndicator(false);
    }
    
    /**
     * Add/Update Web3 sync indicator
     * @param {boolean} isConnected - Whether Web3 wallet is connected
     * @param {string} chainId - Current chain ID
     * @param {string} balance - Token balance
     * @param {string} votingPower - Current voting power
     */
    addWeb3SyncIndicator(isConnected, chainId = null, balance = null, votingPower = null) {
        if (!this.votingPowerContainer) return;
        
        // Remove existing indicator
        const existingIndicator = this.votingPowerContainer.querySelector('.web3-sync-indicator');
        if (existingIndicator) {
            existingIndicator.remove();
        }
        
        if (!isConnected) return;
        
        // Get network info
        const networkName = this.getNetworkName(chainId);
        const networkColor = this.getNetworkColor(chainId);
        
        // Create sync indicator
        const indicator = document.createElement('div');
        indicator.className = 'web3-sync-indicator mt-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800';
        indicator.innerHTML = `
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 rounded-full ${networkColor} animate-pulse"></div>
                    <span class="text-sm font-medium text-green-700 dark:text-green-300">
                        <i class="fas fa-link mr-1"></i>
                        Synced with ${networkName}
                    </span>
                </div>
                <button onclick="window.GovernanceStats.refreshWeb3Data()" 
                        class="text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 transition">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            
            ${balance && votingPower ? `
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="bg-brand-bg-secondary dark:bg-brand-bg-secondary rounded p-2">
                        <div class="text-brand-text-secondary500 dark:text-brand-text-secondary400 mb-1">On-Chain Balance</div>
                        <div class="font-semibold text-brand-text-primary">${balance} GOV</div>
                    </div>
                    <div class="bg-brand-bg-secondary dark:bg-brand-bg-secondary rounded p-2">
                        <div class="text-brand-text-secondary500 dark:text-brand-text-secondary400 mb-1">Voting Power</div>
                        <div class="font-semibold text-brand-text-primary">${votingPower}</div>
                    </div>
                </div>
            ` : ''}
        `;
        
        // Append to container
        this.votingPowerContainer.appendChild(indicator);
    }
    
    /**
     * Get network name from chain ID
     * @param {string} chainId - Chain ID in hex
     * @returns {string} Network name
     */
    getNetworkName(chainId) {
        const networks = {
            '0x89': 'Polygon',
            '0x13882': 'Amoy Testnet',
            '0x1': 'Ethereum',
            '0x38': 'BSC'
        };
        return networks[chainId] || 'Unknown Network';
    }
    
    /**
     * Get network indicator color
     * @param {string} chainId - Chain ID
     * @returns {string} Tailwind color class
     */
    getNetworkColor(chainId) {
        const colors = {
            '0x89': 'bg-purple-500',
            '0x13882': 'bg-orange-500',
            '0x1': 'bg-blue-500',
            '0x38': 'bg-yellow-500'
        };
        return colors[chainId] || 'bg-green-500';
    }
    
    /**
     * Refresh Web3 data manually
     */
    async refreshWeb3Data() {
        if (window.governanceWeb3 && window.governanceWeb3.isConnected()) {
            try {
                if (window.Web3Utils) {
                    window.Web3Utils.showToast('Refreshing blockchain data...', 'info');
                }
                await window.governanceWeb3.syncBalances();
            } catch (error) {
                console.error('Error refreshing Web3 data:', error);
                if (window.Web3Utils) {
                    window.Web3Utils.showToast('Failed to refresh data', 'error');
                }
            }
        }
    }
    
    /**
     * Truncate Ethereum address
     */
    truncateAddress(address) {
        if (!address) return 'N/A';
        return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
    }
    
    /**
     * Setup event listeners for Web3 updates
     */
    setupEventListeners() {
        // Listen for Web3 wallet updates
        document.addEventListener('governance:walletUpdated', (e) => {
            if (e.detail && e.detail.balances) {
                this.updateWeb3Data(e.detail);
            }
        });
        
        // Listen for wallet disconnect
        document.addEventListener('web3:accountDisconnected', () => {
            this.clearWeb3Data();
        });
    }
}

// Export as global
window.GovernanceStats = new GovernanceStats();

// Initialize event listeners
window.GovernanceStats.setupEventListeners();
