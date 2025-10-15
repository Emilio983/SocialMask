/**
 * ============================================
 * GOVERNANCE WEB3 INTEGRATION
 * ============================================
 * Bridge between Web3 modules and Governance frontend
 * 
 * Requires:
 * - Web3Connector
 * - Web3Contracts
 * - Web3Signatures
 * - Web3Utils
 * - GovernanceAPI
 * - GovernanceStats
 * - GovernanceProposals
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

class GovernanceWeb3 {
    constructor() {
        this.initialized = false;
        this.isConnected = false;
        this.currentAccount = null;
        this.currentChainId = null;
    }
    
    /**
     * Initialize Web3 integration
     * @returns {Promise<void>}
     */
    async initialize() {
        if (this.initialized) {
            // console.log('GovernanceWeb3 already initialized');
            return;
        }
        
        try {
            // console.log('Initializing GovernanceWeb3...');
            
            // Check if Web3 modules are loaded
            if (!window.web3Connector || !window.web3Contracts || !window.web3Signatures) {
                throw new Error('Web3 modules not loaded');
            }
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Check for saved connection
            await this.checkSavedConnection();
            
            this.initialized = true;
            // console.log('GovernanceWeb3 initialized');
            
        } catch (error) {
            console.error('Error initializing GovernanceWeb3:', error);
            throw error;
        }
    }
    
    /**
     * Setup event listeners for Web3 events
     */
    setupEventListeners() {
        // Account connected
        document.addEventListener('web3:accountConnected', (event) => {
            // console.log('Account connected:', event.detail);
            this.handleAccountConnected(event.detail);
        });
        
        // Account changed
        document.addEventListener('web3:accountChanged', (event) => {
            // console.log('Account changed:', event.detail);
            this.handleAccountChanged(event.detail);
        });
        
        // Account disconnected
        document.addEventListener('web3:accountDisconnected', () => {
            // console.log('Account disconnected');
            this.handleAccountDisconnected();
        });
        
        // Chain changed
        document.addEventListener('web3:chainChanged', (event) => {
            // console.log('Chain changed:', event.detail);
            this.handleChainChanged(event.detail);
        });
    }
    
    /**
     * Check for saved connection and auto-connect
     * @returns {Promise<void>}
     */
    async checkSavedConnection() {
        try {
            const savedConnection = localStorage.getItem('sphera_web3_connection');
            
            if (savedConnection) {
                const data = JSON.parse(savedConnection);
                const now = Date.now();
                
                // Check if connection is still valid (1 hour)
                if (now - data.timestamp < 3600000) {
                    // console.log('Auto-connecting with saved connection...');
                    await this.connectWallet();
                }
            }
        } catch (error) {
            console.error('Error checking saved connection:', error);
        }
    }
    
    /**
     * Connect wallet and sync with backend
     * @returns {Promise<boolean>} Success status
     */
    async connectWallet() {
        try {
            // Check if Smart Wallet is available
            if (!window.web3Connector.isSmartWalletAvailable()) {
                Web3Utils.showToast('Please configure Smart Wallet to continue', 'error');
                window.open('https://metamask.io/download/', '_blank');
                return false;
            }
            
            // Connect wallet
            const account = await window.web3Connector.connectWallet();
            
            if (!account) {
                Web3Utils.showToast('Failed to connect wallet', 'error');
                return false;
            }
            
            // Initialize contracts
            await window.web3Contracts.initialize();
            
            // Initialize signatures
            await window.web3Signatures.initialize();
            
            // Get chain ID
            const chainId = await window.web3Connector.getCurrentChainId();
            
            // Check if on supported network
            const supportedChains = window.__SPHERA_GOVERNANCE__?.supportedChains || [];
            if (!supportedChains.includes(chainId)) {
                Web3Utils.showToast('Please switch to Polygon or Amoy network', 'warning');
                // Try to switch to Polygon
                await window.web3Connector.switchToPolygon();
                return false;
            }
            
            // Sync with backend
            await this.syncWalletWithBackend(account);
            
            // Update UI
            await this.updateWalletInfo(account, chainId);
            
            this.isConnected = true;
            this.currentAccount = account;
            this.currentChainId = chainId;
            
            Web3Utils.showToast('Wallet connected successfully', 'success');
            return true;
            
        } catch (error) {
            console.error('Error connecting wallet:', error);
            const errorMessage = Web3Utils.handleWeb3Error(error);
            Web3Utils.showToast(errorMessage, 'error');
            return false;
        }
    }
    
    /**
     * Disconnect wallet
     */
    disconnectWallet() {
        window.web3Connector.disconnectWallet();
        this.isConnected = false;
        this.currentAccount = null;
        this.currentChainId = null;
        
        // Update UI
        this.updateDisconnectedUI();
        
        Web3Utils.showToast('Wallet disconnected', 'info');
    }
    
    /**
     * Sync wallet with backend
     * @param {string} address - Wallet address
     * @returns {Promise<void>}
     */
    async syncWalletWithBackend(address) {
        try {
            // Generate signature for verification
            const signatureData = await window.web3Signatures.signWalletVerification(address);
            
            // Send to backend
            const response = await fetch('/api/web3/sync-wallet.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(signatureData)
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Failed to sync wallet');
            }
            
            // console.log('Wallet synced with backend:', result);
            
        } catch (error) {
            console.error('Error syncing wallet with backend:', error);
            // Don't throw - allow connection even if sync fails
        }
    }
    
    /**
     * Update UI with wallet info
     * @param {string} account - Wallet address
     * @param {string} chainId - Chain ID
     * @returns {Promise<void>}
     */
    async updateWalletInfo(account, chainId) {
        try {
            // Get balance
            const balance = await window.web3Contracts.getTokenBalance(account);
            const formattedBalance = Web3Utils.formatBalance(balance, 18, 2);
            
            // Get voting power
            const votingPower = await window.web3Contracts.getVotingPower(account);
            const formattedVotingPower = Web3Utils.formatBalance(votingPower, 18, 2);
            
            // Get delegate
            const delegate = await window.web3Contracts.getDelegates(account);
            
            // Update stats module if available
            if (window.governanceStats) {
                window.governanceStats.updateWeb3Data({
                    address: account,
                    chainId: chainId,
                    balance: formattedBalance,
                    votingPower: formattedVotingPower,
                    delegate: delegate
                });
            }
            
            // Dispatch custom event
            document.dispatchEvent(new CustomEvent('governance:walletUpdated', {
                detail: {
                    address: account,
                    chainId: chainId,
                    balance: formattedBalance,
                    votingPower: formattedVotingPower,
                    delegate: delegate
                }
            }));
            
        } catch (error) {
            console.error('Error updating wallet info:', error);
        }
    }
    
    /**
     * Update UI when disconnected
     */
    updateDisconnectedUI() {
        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('governance:walletDisconnected'));
        
        // Update stats module if available
        if (window.governanceStats) {
            window.governanceStats.clearWeb3Data();
        }
    }
    
    /**
     * Handle account connected event
     * @param {object} data - Event data
     */
    async handleAccountConnected(data) {
        this.isConnected = true;
        this.currentAccount = data.account;
        this.currentChainId = data.chainId;
        
        await this.updateWalletInfo(data.account, data.chainId);
    }
    
    /**
     * Handle account changed event
     * @param {object} data - Event data
     */
    async handleAccountChanged(data) {
        this.currentAccount = data.newAccount;
        
        // Sync new account with backend
        await this.syncWalletWithBackend(data.newAccount);
        
        // Update UI
        await this.updateWalletInfo(data.newAccount, this.currentChainId);
        
        // Reload proposals to show new voting power
        if (window.governanceProposals) {
            await window.governanceProposals.loadProposals();
        }
    }
    
    /**
     * Handle account disconnected event
     */
    handleAccountDisconnected() {
        this.isConnected = false;
        this.currentAccount = null;
        this.currentChainId = null;
        
        this.updateDisconnectedUI();
    }
    
    /**
     * Handle chain changed event
     * @param {object} data - Event data
     */
    async handleChainChanged(data) {
        this.currentChainId = data.newChainId;
        
        // Check if on supported network
        const supportedChains = window.__SPHERA_GOVERNANCE__?.supportedChains || [];
        if (!supportedChains.includes(data.newChainId)) {
            Web3Utils.showToast('Unsupported network. Please switch to Polygon or Amoy', 'warning');
            return;
        }
        
        // Reinitialize contracts on new network
        await window.web3Contracts.initialize();
        await window.web3Signatures.initialize();
        
        // Update UI
        if (this.currentAccount) {
            await this.updateWalletInfo(this.currentAccount, data.newChainId);
        }
    }
    
    /**
     * Vote with signature (off-chain)
     * @param {number} proposalId - Proposal ID
     * @param {number} support - Vote support (0=against, 1=for, 2=abstain)
     * @param {string} reason - Optional vote reason
     * @returns {Promise<boolean>} Success status
     */
    async voteWithSignature(proposalId, support, reason = '') {
        if (!this.isConnected) {
            Web3Utils.showToast('Please connect wallet first', 'warning');
            await this.connectWallet();
            return false;
        }
        
        try {
            // Sign vote
            const signatureData = await window.web3Signatures.signVote(
                proposalId,
                support,
                this.currentAccount,
                reason
            );
            
            // Submit to backend
            const response = await window.governanceAPI.castVote({
                proposal_id: proposalId,
                vote: support === 1 ? 'for' : support === 2 ? 'abstain' : 'against',
                reason: reason,
                signature: signatureData.signature,
                nonce: signatureData.nonce,
                timestamp: signatureData.timestamp
            });
            
            if (!response.success) {
                throw new Error(response.error || 'Failed to submit vote');
            }
            
            Web3Utils.showToast('Vote submitted successfully', 'success');
            
            // Reload proposals
            if (window.governanceProposals) {
                await window.governanceProposals.loadProposals();
            }
            
            return true;
            
        } catch (error) {
            console.error('Error voting with signature:', error);
            const errorMessage = Web3Utils.handleWeb3Error(error);
            Web3Utils.showToast(errorMessage, 'error');
            return false;
        }
    }
    
    /**
     * Vote on-chain
     * @param {number} proposalId - Proposal ID
     * @param {number} support - Vote support
     * @param {string} reason - Optional vote reason
     * @returns {Promise<boolean>} Success status
     */
    async voteOnChain(proposalId, support, reason = '') {
        if (!this.isConnected) {
            Web3Utils.showToast('Please connect wallet first', 'warning');
            await this.connectWallet();
            return false;
        }
        
        try {
            // Cast vote on-chain
            let result;
            if (reason) {
                result = await window.web3Contracts.castVoteWithReason(proposalId, support, reason);
            } else {
                result = await window.web3Contracts.castVoteOnChain(proposalId, support);
            }
            
            if (!result.success) {
                throw new Error('Transaction failed');
            }
            
            // Show success with tx link
            const chainId = this.currentChainId;
            const explorerUrl = Web3Utils.getTxExplorerUrl(result.txHash, chainId);
            Web3Utils.showToast(
                `Vote submitted! <a href="${explorerUrl}" target="_blank">View Transaction</a>`,
                'success'
            );
            
            // Reload proposals
            if (window.governanceProposals) {
                await window.governanceProposals.loadProposals();
            }
            
            return true;
            
        } catch (error) {
            console.error('Error voting on-chain:', error);
            const errorMessage = Web3Utils.handleWeb3Error(error);
            Web3Utils.showToast(errorMessage, 'error');
            return false;
        }
    }
    
    /**
     * Delegate voting power with signature (off-chain)
     * @param {string} delegatee - Address to delegate to
     * @returns {Promise<boolean>} Success status
     */
    async delegateWithSignature(delegatee) {
        if (!this.isConnected) {
            Web3Utils.showToast('Please connect wallet first', 'warning');
            await this.connectWallet();
            return false;
        }
        
        try {
            // Sign delegation
            const signatureData = await window.web3Signatures.signDelegation(
                this.currentAccount,
                delegatee
            );
            
            // Submit to backend
            const response = await window.governanceAPI.delegate({
                delegatee: delegatee,
                signature: signatureData.signature,
                nonce: signatureData.nonce,
                timestamp: signatureData.timestamp
            });
            
            if (!response.success) {
                throw new Error(response.error || 'Failed to delegate');
            }
            
            Web3Utils.showToast('Delegation submitted successfully', 'success');
            
            // Update wallet info
            await this.updateWalletInfo(this.currentAccount, this.currentChainId);
            
            return true;
            
        } catch (error) {
            console.error('Error delegating with signature:', error);
            const errorMessage = Web3Utils.handleWeb3Error(error);
            Web3Utils.showToast(errorMessage, 'error');
            return false;
        }
    }
    
    /**
     * Delegate on-chain
     * @param {string} delegatee - Address to delegate to
     * @returns {Promise<boolean>} Success status
     */
    async delegateOnChain(delegatee) {
        if (!this.isConnected) {
            Web3Utils.showToast('Please connect wallet first', 'warning');
            await this.connectWallet();
            return false;
        }
        
        try {
            // Delegate on-chain
            const result = await window.web3Contracts.delegateOnChain(delegatee);
            
            if (!result.success) {
                throw new Error('Transaction failed');
            }
            
            // Show success with tx link
            const chainId = this.currentChainId;
            const explorerUrl = Web3Utils.getTxExplorerUrl(result.txHash, chainId);
            Web3Utils.showToast(
                `Delegation successful! <a href="${explorerUrl}" target="_blank">View Transaction</a>`,
                'success'
            );
            
            // Update wallet info
            await this.updateWalletInfo(this.currentAccount, this.currentChainId);
            
            return true;
            
        } catch (error) {
            console.error('Error delegating on-chain:', error);
            const errorMessage = Web3Utils.handleWeb3Error(error);
            Web3Utils.showToast(errorMessage, 'error');
            return false;
        }
    }
}

// Export as singleton
window.GovernanceWeb3 = GovernanceWeb3;
window.governanceWeb3 = new GovernanceWeb3();

// console.log('GovernanceWeb3 module loaded');
