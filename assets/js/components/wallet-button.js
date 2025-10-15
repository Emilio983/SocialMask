/**
 * ============================================
 * WALLET BUTTON CONTROLLER
 * ============================================
 * Manages wallet button UI states and interactions
 * 
 * Requires:
 * - web3Connector (Web3Connector instance)
 * - web3Contracts (Web3Contracts instance)
 * - Web3Utils
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

class WalletButton {
    constructor() {
        this.currentState = 'checking'; // checking, not-installed, not-connected, connecting, connected
        this.currentAddress = null;
        this.currentChainId = null;
        this.balances = {
            gov: '0',
            votingPower: '0',
            native: '0'
        };
        
        this.elements = {};
        this.dropdownOpen = false;
        
        this.init();
    }
    
    /**
     * Initialize wallet button
     */
    init() {
        // console.log('Initializing WalletButton...');
        
        // Cache DOM elements
        this.cacheElements();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Check initial state
        this.checkWalletState();
        
        // console.log('WalletButton initialized');
    }
    
    /**
     * Cache DOM elements
     */
    cacheElements() {
        this.elements = {
            container: document.getElementById('wallet-connect-container'),
            notInstalled: document.getElementById('wallet-not-installed'),
            notConnected: document.getElementById('wallet-not-connected'),
            connecting: document.getElementById('wallet-connecting'),
            connected: document.getElementById('wallet-connected'),
            
            connectBtn: document.getElementById('connect-wallet-btn'),
            dropdownBtn: document.getElementById('wallet-dropdown-btn'),
            dropdownMenu: document.getElementById('wallet-dropdown-menu'),
            
            addressDisplay: document.getElementById('wallet-address-display'),
            fullAddress: document.getElementById('wallet-full-address'),
            balanceBadge: document.getElementById('wallet-balance-badge'),
            
            govBalance: document.getElementById('wallet-gov-balance'),
            votingPower: document.getElementById('wallet-voting-power'),
            nativeBalance: document.getElementById('wallet-native-balance'),
            nativeSymbol: document.getElementById('native-token-symbol'),
            
            copyBtn: document.getElementById('copy-address-btn'),
            copyBtn2: document.getElementById('copy-address-btn-2'),
            viewExplorerBtn: document.getElementById('view-explorer-btn'),
            changeAccountBtn: document.getElementById('change-account-btn'),
            refreshBtn: document.getElementById('refresh-balances-btn'),
            disconnectBtn: document.getElementById('disconnect-wallet-btn'),
            
            networkBadgeDropdown: document.getElementById('network-badge-dropdown')
        };
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Connect button
        if (this.elements.connectBtn) {
            this.elements.connectBtn.addEventListener('click', () => this.handleConnect());
        }
        
        // Dropdown toggle
        if (this.elements.dropdownBtn) {
            this.elements.dropdownBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
        }
        
        // Copy address buttons
        [this.elements.copyBtn, this.elements.copyBtn2].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', () => this.copyAddress());
            }
        });
        
        // Change account
        if (this.elements.changeAccountBtn) {
            this.elements.changeAccountBtn.addEventListener('click', () => this.changeAccount());
        }
        
        // Refresh balances
        if (this.elements.refreshBtn) {
            this.elements.refreshBtn.addEventListener('click', () => this.refreshBalances());
        }
        
        // Disconnect
        if (this.elements.disconnectBtn) {
            this.elements.disconnectBtn.addEventListener('click', () => this.disconnect());
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (this.dropdownOpen && !this.elements.connected.contains(e.target)) {
                this.closeDropdown();
            }
        });
        
        // Listen to Web3 events
        document.addEventListener('web3:accountConnected', (e) => {
            this.handleAccountConnected(e.detail);
        });
        
        document.addEventListener('web3:accountChanged', (e) => {
            this.handleAccountChanged(e.detail);
        });
        
        document.addEventListener('web3:accountDisconnected', () => {
            this.handleAccountDisconnected();
        });
        
        document.addEventListener('web3:chainChanged', (e) => {
            this.handleChainChanged(e.detail);
        });
        
        document.addEventListener('governance:walletUpdated', (e) => {
            this.updateBalancesFromEvent(e.detail);
        });
    }
    
    /**
     * Check wallet state
     */
    async checkWalletState() {
        try {
            // Check if Smart Wallet is available
            if (!window.web3Connector || !window.web3Connector.isSmartWalletAvailable()) {
                this.setState('not-installed');
                return;
            }
            
            // Check if already connected
            const account = await window.web3Connector.getCurrentAccount();
            
            if (account) {
                this.setState('connected');
                this.currentAddress = account;
                this.currentChainId = await window.web3Connector.getCurrentChainId();
                this.updateDisplay();
                await this.loadBalances();
            } else {
                this.setState('not-connected');
            }
            
        } catch (error) {
            console.error('Error checking wallet state:', error);
            this.setState('not-connected');
        }
    }
    
    /**
     * Set button state
     */
    setState(state) {
        this.currentState = state;
        
        // Hide all states
        Object.values(this.elements).forEach(el => {
            if (el && el.classList && el.classList.contains('hidden') === false) {
                el.classList.add('hidden');
            }
        });
        
        // Show current state
        switch(state) {
            case 'not-installed':
                this.elements.notInstalled?.classList.remove('hidden');
                break;
            case 'not-connected':
                this.elements.notConnected?.classList.remove('hidden');
                break;
            case 'connecting':
                this.elements.connecting?.classList.remove('hidden');
                break;
            case 'connected':
                this.elements.connected?.classList.remove('hidden');
                break;
        }
    }
    
    /**
     * Handle connect button click
     */
    async handleConnect() {
        try {
            this.setState('connecting');
            
            if (window.governanceWeb3) {
                const success = await window.governanceWeb3.connectWallet();
                if (!success) {
                    this.setState('not-connected');
                }
            } else {
                throw new Error('GovernanceWeb3 not available');
            }
            
        } catch (error) {
            console.error('Error connecting wallet:', error);
            const errorMsg = Web3Utils.handleWeb3Error(error);
            Web3Utils.showToast(errorMsg, 'error');
            this.setState('not-connected');
        }
    }
    
    /**
     * Handle account connected
     */
    handleAccountConnected(data) {
        this.currentAddress = data.account;
        this.currentChainId = data.chainId;
        this.setState('connected');
        this.updateDisplay();
        this.loadBalances();
    }
    
    /**
     * Handle account changed
     */
    handleAccountChanged(data) {
        this.currentAddress = data.newAccount;
        this.updateDisplay();
        this.loadBalances();
    }
    
    /**
     * Handle account disconnected
     */
    handleAccountDisconnected() {
        this.currentAddress = null;
        this.currentChainId = null;
        this.balances = { gov: '0', votingPower: '0', native: '0' };
        this.setState('not-connected');
        this.closeDropdown();
    }
    
    /**
     * Handle chain changed
     */
    handleChainChanged(data) {
        this.currentChainId = data.newChainId;
        this.updateDisplay();
        this.loadBalances();
    }
    
    /**
     * Update display with current address
     */
    updateDisplay() {
        if (!this.currentAddress) return;
        
        const truncated = Web3Utils.truncateAddress(this.currentAddress, 6, 4);
        
        if (this.elements.addressDisplay) {
            this.elements.addressDisplay.textContent = truncated;
        }
        
        if (this.elements.fullAddress) {
            this.elements.fullAddress.textContent = this.currentAddress;
        }
        
        // Update explorer link
        if (this.elements.viewExplorerBtn && this.currentChainId) {
            const explorerUrl = Web3Utils.getExplorerUrl(this.currentAddress, this.currentChainId);
            this.elements.viewExplorerBtn.href = explorerUrl;
        }
        
        // Update native token symbol
        const networkName = Web3Utils.getNetworkName(this.currentChainId);
        const symbol = networkName === 'Polygon' || networkName === 'Amoy Testnet' ? 'MATIC' : 'ETH';
        if (this.elements.nativeSymbol) {
            this.elements.nativeSymbol.textContent = symbol;
        }
    }
    
    /**
     * Load balances
     */
    async loadBalances() {
        if (!this.currentAddress || !window.web3Contracts) return;
        
        try {
            // Get GOV balance
            const govBalance = await window.web3Contracts.getTokenBalance(this.currentAddress);
            this.balances.gov = Web3Utils.formatBalance(govBalance, 18, 2);
            
            // Get voting power
            const votingPower = await window.web3Contracts.getVotingPower(this.currentAddress);
            this.balances.votingPower = Web3Utils.formatBalance(votingPower, 18, 2);
            
            // Get native balance
            const nativeBalance = await window.web3Connector.getBalance(this.currentAddress);
            this.balances.native = Web3Utils.formatBalance(nativeBalance, 18, 4);
            
            this.updateBalancesDisplay();
            
        } catch (error) {
            console.error('Error loading balances:', error);
        }
    }
    
    /**
     * Update balances display
     */
    updateBalancesDisplay() {
        if (this.elements.balanceBadge) {
            this.elements.balanceBadge.textContent = `${this.balances.gov} GOV`;
        }
        
        if (this.elements.govBalance) {
            this.elements.govBalance.textContent = this.balances.gov;
        }
        
        if (this.elements.votingPower) {
            this.elements.votingPower.textContent = this.balances.votingPower;
        }
        
        if (this.elements.nativeBalance) {
            this.elements.nativeBalance.textContent = this.balances.native;
        }
    }
    
    /**
     * Update balances from event
     */
    updateBalancesFromEvent(data) {
        if (data.balance) {
            this.balances.gov = data.balance;
        }
        if (data.votingPower) {
            this.balances.votingPower = data.votingPower;
        }
        this.updateBalancesDisplay();
    }
    
    /**
     * Copy address to clipboard
     */
    async copyAddress() {
        if (!this.currentAddress) return;
        
        const success = await Web3Utils.copyToClipboard(this.currentAddress);
        
        if (success) {
            Web3Utils.showToast('Address copied to clipboard', 'success');
            
            // Visual feedback
            [this.elements.copyBtn, this.elements.copyBtn2].forEach(btn => {
                if (btn) {
                    btn.classList.add('copy-success');
                    setTimeout(() => btn.classList.remove('copy-success'), 300);
                }
            });
        } else {
            Web3Utils.showToast('Failed to copy address', 'error');
        }
    }
    
    /**
     * Change account (re-trigger Smart Wallet)
     */
    async changeAccount() {
        this.closeDropdown();
        await this.handleConnect();
    }
    
    /**
     * Refresh balances
     */
    async refreshBalances() {
        if (this.elements.refreshBtn) {
            const icon = this.elements.refreshBtn.querySelector('svg');
            icon?.classList.add('animate-spin');
        }
        
        await this.loadBalances();
        
        if (this.elements.refreshBtn) {
            const icon = this.elements.refreshBtn.querySelector('svg');
            icon?.classList.remove('animate-spin');
        }
        
        Web3Utils.showToast('Balances refreshed', 'success');
    }
    
    /**
     * Disconnect wallet
     */
    disconnect() {
        if (window.governanceWeb3) {
            window.governanceWeb3.disconnectWallet();
        }
        this.closeDropdown();
    }
    
    /**
     * Toggle dropdown
     */
    toggleDropdown() {
        if (this.dropdownOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }
    
    /**
     * Open dropdown
     */
    openDropdown() {
        if (this.elements.dropdownMenu) {
            this.elements.dropdownMenu.classList.remove('hidden');
            this.dropdownOpen = true;
        }
    }
    
    /**
     * Close dropdown
     */
    closeDropdown() {
        if (this.elements.dropdownMenu) {
            this.elements.dropdownMenu.classList.add('hidden');
            this.dropdownOpen = false;
        }
    }
}

// Export as singleton
window.WalletButton = WalletButton;
window.walletButton = new WalletButton();

// console.log('WalletButton module loaded');
