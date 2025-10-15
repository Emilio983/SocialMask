/**
 * ============================================
 * WEB3 CONNECTOR
 * ============================================
 * Módulo para conectar y gestionar Smart Wallet
 * 
 * Features:
 * - Detectar Smart Wallet
 * - Conectar wallet
 * - Detectar cuenta y red
 * - Manejar cambios de cuenta/red
 * - Persistir conexión
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

class Web3Connector {
    constructor() {
        this.ethereum = window.smartWalletProvider;
        this.currentAccount = null;
        this.currentChainId = null;
        this.isConnected = false;
        
        // Supported networks
        this.networks = {
            '0x89': {
                name: 'Polygon Mainnet',
                chainId: 137,
                rpcUrl: 'https://polygon-rpc.com',
                blockExplorer: 'https://polygonscan.com',
                nativeCurrency: { name: 'MATIC', symbol: 'MATIC', decimals: 18 }
            },
            '0x13882': {
                name: 'Polygon Amoy Testnet',
                chainId: 80002,
                rpcUrl: 'https://rpc-amoy.polygon.technology',
                blockExplorer: 'https://www.oklink.com/amoy',
                nativeCurrency: { name: 'MATIC', symbol: 'MATIC', decimals: 18 }
            },
            '0x1': {
                name: 'Ethereum Mainnet',
                chainId: 1,
                rpcUrl: 'https://eth-mainnet.g.alchemy.com/v2/demo',
                blockExplorer: 'https://etherscan.io',
                nativeCurrency: { name: 'Ether', symbol: 'ETH', decimals: 18 }
            }
        };
        
        // Load saved connection state
        this.loadConnectionState();
        
        // Setup event listeners
        this.setupEventListeners();
    }
    
    /**
     * Check if Smart Wallet is available
     * @returns {boolean}
     */
    isSmartWalletAvailable() {
        return typeof this.ethereum !== 'undefined' && this.ethereum.isSmartWallet;
    }
    
    /**
     * Connect wallet and request accounts
     * @returns {Promise<string>} Connected account address
     */
    async connectWallet() {
        try {
            // Check Smart Wallet availability
            if (!this.isSmartWalletAvailable()) {
                throw new Error('Smart Wallet no disponible. Por favor contacta a soporte.');
            }
            
            // console.log('Requesting accounts...');
            
            // Request accounts
            const accounts = await this.ethereum.request({
                method: 'eth_requestAccounts'
            });
            
            if (!accounts || accounts.length === 0) {
                throw new Error('No accounts found. Please unlock Smart Wallet.');
            }
            
            this.currentAccount = accounts[0];
            // console.log('Connected account:', this.currentAccount);
            
            // Get current chain
            const chainId = await this.ethereum.request({
                method: 'eth_chainId'
            });
            this.currentChainId = chainId;
            // console.log('Current chain:', chainId, this.getNetworkName(chainId));
            
            // Mark as connected
            this.isConnected = true;
            
            // Save connection state
            this.saveConnectionState();
            
            // Trigger connection event
            this.triggerEvent('accountConnected', {
                account: this.currentAccount,
                chainId: this.currentChainId,
                networkName: this.getNetworkName(this.currentChainId)
            });
            
            return this.currentAccount;
            
        } catch (error) {
            console.error('Error connecting wallet:', error);
            this.isConnected = false;
            throw error;
        }
    }
    
    /**
     * Disconnect wallet
     */
    async disconnectWallet() {
        this.currentAccount = null;
        this.currentChainId = null;
        this.isConnected = false;
        
        // Clear saved state
        this.clearConnectionState();
        
        // Trigger disconnection event
        this.triggerEvent('accountDisconnected', {});
        
        // console.log('Wallet disconnected');
    }
    
    /**
     * Get current connected account
     * @returns {Promise<string|null>}
     */
    async getCurrentAccount() {
        if (!this.isSmartWalletAvailable()) {
            return null;
        }
        
        try {
            const accounts = await this.ethereum.request({
                method: 'eth_accounts'
            });
            
            if (accounts && accounts.length > 0) {
                this.currentAccount = accounts[0];
                return this.currentAccount;
            }
            
            return null;
        } catch (error) {
            console.error('Error getting current account:', error);
            return null;
        }
    }
    
    /**
     * Get current chain ID
     * @returns {Promise<string>}
     */
    async getCurrentChainId() {
        if (!this.isSmartWalletAvailable()) {
            return null;
        }
        
        try {
            const chainId = await this.ethereum.request({
                method: 'eth_chainId'
            });
            this.currentChainId = chainId;
            return chainId;
        } catch (error) {
            console.error('Error getting chain ID:', error);
            return null;
        }
    }
    
    /**
     * Get network name from chain ID
     * @param {string} chainId - Chain ID in hex format
     * @returns {string}
     */
    getNetworkName(chainId) {
        const network = this.networks[chainId];
        return network ? network.name : `Unknown Network (${chainId})`;
    }
    
    /**
     * Get network info
     * @param {string} chainId
     * @returns {object|null}
     */
    getNetworkInfo(chainId) {
        return this.networks[chainId] || null;
    }
    
    /**
     * Check if current network is supported
     * @returns {boolean}
     */
    isNetworkSupported() {
        return this.currentChainId && this.networks[this.currentChainId] !== undefined;
    }
    
    /**
     * Switch to Polygon Mainnet
     * @returns {Promise<void>}
     */
    async switchToPolygon() {
        return this.switchNetwork('0x89');
    }
    
    /**
     * Switch to Polygon Amoy Testnet
     * @returns {Promise<void>}
     */
    async switchToAmoy() {
        return this.switchNetwork('0x13882');
    }
    
    /**
     * Switch to specific network
     * @param {string} chainId - Chain ID in hex format
     * @returns {Promise<void>}
     */
    async switchNetwork(chainId) {
        if (!this.isSmartWalletAvailable()) {
            throw new Error('Smart Wallet not available');
        }
        
        const network = this.networks[chainId];
        if (!network) {
            throw new Error(`Network ${chainId} not supported`);
        }
        
        try {
            // Try to switch to the network
            await this.ethereum.request({
                method: 'wallet_switchEthereumChain',
                params: [{ chainId: chainId }]
            });
            
            // console.log(`Switched to ${network.name}`);
            
        } catch (switchError) {
            // Network not added, try to add it
            if (switchError.code === 4902) {
                try {
                    await this.ethereum.request({
                        method: 'wallet_addEthereumChain',
                        params: [{
                            chainId: chainId,
                            chainName: network.name,
                            rpcUrls: [network.rpcUrl],
                            blockExplorerUrls: [network.blockExplorer],
                            nativeCurrency: network.nativeCurrency
                        }]
                    });
                    
                    // console.log(`Added and switched to ${network.name}`);
                    
                } catch (addError) {
                    console.error('Error adding network:', addError);
                    throw addError;
                }
            } else {
                console.error('Error switching network:', switchError);
                throw switchError;
            }
        }
    }
    
    /**
     * Setup event listeners for Smart Wallet
     */
    setupEventListeners() {
        if (!this.isSmartWalletAvailable()) {
            return;
        }
        
        // Listen for account changes
        this.ethereum.on('accountsChanged', (accounts) => {
            // console.log('Accounts changed:', accounts);
            
            if (accounts.length === 0) {
                // User disconnected
                this.disconnectWallet();
            } else {
                // Account switched
                const oldAccount = this.currentAccount;
                this.currentAccount = accounts[0];
                this.saveConnectionState();
                
                this.triggerEvent('accountChanged', {
                    oldAccount: oldAccount,
                    newAccount: this.currentAccount
                });
            }
        });
        
        // Listen for chain changes
        this.ethereum.on('chainChanged', (chainId) => {
            // console.log('Chain changed:', chainId);
            
            const oldChainId = this.currentChainId;
            this.currentChainId = chainId;
            this.saveConnectionState();
            
            this.triggerEvent('chainChanged', {
                oldChainId: oldChainId,
                newChainId: chainId,
                networkName: this.getNetworkName(chainId),
                isSupported: this.isNetworkSupported()
            });
            
            // Reload page on chain change (recommended by Smart Wallet)
            window.location.reload();
        });
        
        // Listen for connection
        this.ethereum.on('connect', (connectInfo) => {
            // console.log('Smart Wallet connected:', connectInfo);
            this.currentChainId = connectInfo.chainId;
        });
        
        // Listen for disconnection
        this.ethereum.on('disconnect', (error) => {
            // console.log('Smart Wallet disconnected:', error);
            this.disconnectWallet();
        });
    }
    
    /**
     * Save connection state to localStorage
     */
    saveConnectionState() {
        try {
            const state = {
                isConnected: this.isConnected,
                account: this.currentAccount,
                chainId: this.currentChainId,
                timestamp: Date.now()
            };
            localStorage.setItem('sphera_web3_connection', JSON.stringify(state));
        } catch (error) {
            console.error('Error saving connection state:', error);
        }
    }
    
    /**
     * Load connection state from localStorage
     */
    loadConnectionState() {
        try {
            const stateStr = localStorage.getItem('sphera_web3_connection');
            if (stateStr) {
                const state = JSON.parse(stateStr);
                
                // Check if state is not too old (1 hour)
                const age = Date.now() - state.timestamp;
                if (age < 3600000) {
                    this.isConnected = state.isConnected;
                    this.currentAccount = state.account;
                    this.currentChainId = state.chainId;
                    
                    // console.log('Loaded connection state:', state);
                } else {
                    // console.log('Connection state expired, clearing...');
                    this.clearConnectionState();
                }
            }
        } catch (error) {
            console.error('Error loading connection state:', error);
        }
    }
    
    /**
     * Clear connection state from localStorage
     */
    clearConnectionState() {
        try {
            localStorage.removeItem('sphera_web3_connection');
        } catch (error) {
            console.error('Error clearing connection state:', error);
        }
    }
    
    /**
     * Trigger custom event
     * @param {string} eventName
     * @param {object} detail
     */
    triggerEvent(eventName, detail) {
        const event = new CustomEvent(`web3:${eventName}`, {
            detail: detail,
            bubbles: true
        });
        window.dispatchEvent(event);
    }
    
    /**
     * Get account balance in ETH/MATIC
     * @param {string} address
     * @returns {Promise<string>}
     */
    async getBalance(address = null) {
        if (!this.isSmartWalletAvailable()) {
            throw new Error('Smart Wallet not available');
        }
        
        const targetAddress = address || this.currentAccount;
        if (!targetAddress) {
            throw new Error('No address provided');
        }
        
        try {
            const balance = await this.ethereum.request({
                method: 'eth_getBalance',
                params: [targetAddress, 'latest']
            });
            
            // Convert from wei to ether
            const balanceInEth = parseInt(balance, 16) / 1e18;
            return balanceInEth.toFixed(4);
            
        } catch (error) {
            console.error('Error getting balance:', error);
            throw error;
        }
    }
    
    /**
     * Sign message with Smart Wallet
     * @param {string} message
     * @returns {Promise<string>} Signature
     */
    async signMessage(message) {
        if (!this.isSmartWalletAvailable()) {
            throw new Error('Smart Wallet not available');
        }
        
        if (!this.currentAccount) {
            throw new Error('No account connected');
        }
        
        try {
            const signature = await this.ethereum.request({
                method: 'personal_sign',
                params: [message, this.currentAccount]
            });
            
            // console.log('Message signed successfully');
            return signature;
            
        } catch (error) {
            console.error('Error signing message:', error);
            throw error;
        }
    }
}

// Export as singleton
window.Web3Connector = Web3Connector;
window.web3Connector = new Web3Connector();

// console.log('Web3Connector initialized');
