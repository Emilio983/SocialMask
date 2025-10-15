/**
 * ============================================
 * WEB3 CONTRACTS
 * ============================================
 * Interacción con contratos inteligentes
 * 
 * Requires:
 * - Ethers.js (loaded via CDN)
 * - Web3Utils
 * - Web3Connector
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

class Web3Contracts {
    constructor() {
        this.provider = null;
        this.signer = null;
        this.contracts = {
            governor: null,
            token: null,
            timelock: null
        };
        
        // Contract addresses from config
        this.addresses = window.__SPHERA_GOVERNANCE__?.contracts || {};
        
        // Load ABIs
        this.abis = {
            governor: null,
            token: null
        };
        
        this.initialized = false;
    }
    
    /**
     * Initialize contracts with provider
     * @returns {Promise<void>}
     */
    async initialize() {
        if (this.initialized) {
            // console.log('Web3Contracts already initialized');
            return;
        }
        
        try {
            // Check if ethers is loaded
            if (typeof ethers === 'undefined') {
                throw new Error('Ethers.js not loaded');
            }
            
            // Check if Smart Wallet is connected
            if (!window.smartWalletProvider) {
                throw new Error('Smart Wallet not found');
            }
            
            // Create provider
            this.provider = new ethers.providers.Web3Provider(window.smartWalletProvider);
            this.signer = this.provider.getSigner();
            
            // console.log('Web3 provider initialized');
            
            // Load ABIs
            await this.loadABIs();
            
            // Initialize contracts
            await this.initializeContracts();
            
            this.initialized = true;
            // console.log('Web3Contracts initialized successfully');
            
        } catch (error) {
            console.error('Error initializing Web3Contracts:', error);
            throw error;
        }
    }
    
    /**
     * Load contract ABIs
     * @returns {Promise<void>}
     */
    async loadABIs() {
        try {
            // Load Governor ABI
            const governorResponse = await fetch('../assets/js/web3/abis/Governor.json');
            this.abis.governor = await governorResponse.json();
            
            // Load GovernanceToken ABI
            const tokenResponse = await fetch('../assets/js/web3/abis/GovernanceToken.json');
            this.abis.token = await tokenResponse.json();
            
            // console.log('ABIs loaded successfully');
        } catch (error) {
            console.error('Error loading ABIs:', error);
            throw error;
        }
    }
    
    /**
     * Initialize contract instances
     * @returns {Promise<void>}
     */
    async initializeContracts() {
        try {
            // Governor contract
            if (this.addresses.governor && this.addresses.governor !== '0x0000000000000000000000000000000000000000') {
                this.contracts.governor = new ethers.Contract(
                    this.addresses.governor,
                    this.abis.governor,
                    this.signer
                );
                // console.log('Governor contract initialized:', this.addresses.governor);
            }
            
            // GovernanceToken contract
            if (this.addresses.token && this.addresses.token !== '0x0000000000000000000000000000000000000000') {
                this.contracts.token = new ethers.Contract(
                    this.addresses.token,
                    this.abis.token,
                    this.signer
                );
                // console.log('Token contract initialized:', this.addresses.token);
            }
            
        } catch (error) {
            console.error('Error initializing contracts:', error);
            throw error;
        }
    }
    
    /**
     * Get token balance of address
     * @param {string} address
     * @returns {Promise<string>} Balance in wei
     */
    async getTokenBalance(address) {
        if (!this.contracts.token) {
            throw new Error('Token contract not initialized');
        }
        
        try {
            const balance = await this.contracts.token.balanceOf(address);
            return balance.toString();
        } catch (error) {
            console.error('Error getting token balance:', error);
            throw error;
        }
    }
    
    /**
     * Get voting power of address
     * @param {string} address
     * @returns {Promise<string>} Voting power in wei
     */
    async getVotingPower(address) {
        if (!this.contracts.token) {
            throw new Error('Token contract not initialized');
        }
        
        try {
            const votes = await this.contracts.token.getVotes(address);
            return votes.toString();
        } catch (error) {
            console.error('Error getting voting power:', error);
            throw error;
        }
    }
    
    /**
     * Get delegate of address
     * @param {string} address
     * @returns {Promise<string>} Delegatee address
     */
    async getDelegates(address) {
        if (!this.contracts.token) {
            throw new Error('Token contract not initialized');
        }
        
        try {
            const delegatee = await this.contracts.token.delegates(address);
            return delegatee;
        } catch (error) {
            console.error('Error getting delegates:', error);
            throw error;
        }
    }
    
    /**
     * Delegate voting power on-chain
     * @param {string} delegatee - Address to delegate to
     * @returns {Promise<object>} Transaction receipt
     */
    async delegateOnChain(delegatee) {
        if (!this.contracts.token) {
            throw new Error('Token contract not initialized');
        }
        
        try {
            // console.log('Delegating to:', delegatee);
            
            // Send transaction
            const tx = await this.contracts.token.delegate(delegatee);
            // console.log('Delegation transaction sent:', tx.hash);
            
            // Wait for confirmation
            const receipt = await tx.wait();
            // console.log('Delegation confirmed:', receipt);
            
            return {
                success: true,
                txHash: tx.hash,
                blockNumber: receipt.blockNumber
            };
            
        } catch (error) {
            console.error('Error delegating on-chain:', error);
            throw error;
        }
    }
    
    /**
     * Get proposal state from chain
     * @param {string} proposalId
     * @returns {Promise<number>} State number
     */
    async getProposalState(proposalId) {
        if (!this.contracts.governor) {
            throw new Error('Governor contract not initialized');
        }
        
        try {
            const state = await this.contracts.governor.state(proposalId);
            return state;
        } catch (error) {
            console.error('Error getting proposal state:', error);
            throw error;
        }
    }
    
    /**
     * Get proposal votes from chain
     * @param {string} proposalId
     * @returns {Promise<object>} Votes object
     */
    async getProposalVotes(proposalId) {
        if (!this.contracts.governor) {
            throw new Error('Governor contract not initialized');
        }
        
        try {
            const votes = await this.contracts.governor.proposalVotes(proposalId);
            return {
                againstVotes: votes.againstVotes.toString(),
                forVotes: votes.forVotes.toString(),
                abstainVotes: votes.abstainVotes.toString()
            };
        } catch (error) {
            console.error('Error getting proposal votes:', error);
            throw error;
        }
    }
    
    /**
     * Cast vote on-chain
     * @param {string} proposalId
     * @param {number} support - 0=against, 1=for, 2=abstain
     * @returns {Promise<object>} Transaction receipt
     */
    async castVoteOnChain(proposalId, support) {
        if (!this.contracts.governor) {
            throw new Error('Governor contract not initialized');
        }
        
        try {
            // console.log('Casting vote on-chain:', { proposalId, support });
            
            // Send transaction
            const tx = await this.contracts.governor.castVote(proposalId, support);
            // console.log('Vote transaction sent:', tx.hash);
            
            // Wait for confirmation
            const receipt = await tx.wait();
            // console.log('Vote confirmed:', receipt);
            
            return {
                success: true,
                txHash: tx.hash,
                blockNumber: receipt.blockNumber
            };
            
        } catch (error) {
            console.error('Error casting vote on-chain:', error);
            throw error;
        }
    }
    
    /**
     * Cast vote with reason on-chain
     * @param {string} proposalId
     * @param {number} support
     * @param {string} reason
     * @returns {Promise<object>} Transaction receipt
     */
    async castVoteWithReason(proposalId, support, reason) {
        if (!this.contracts.governor) {
            throw new Error('Governor contract not initialized');
        }
        
        try {
            // console.log('Casting vote with reason:', { proposalId, support, reason });
            
            const tx = await this.contracts.governor.castVoteWithReason(proposalId, support, reason);
            // console.log('Vote transaction sent:', tx.hash);
            
            const receipt = await tx.wait();
            // console.log('Vote confirmed:', receipt);
            
            return {
                success: true,
                txHash: tx.hash,
                blockNumber: receipt.blockNumber
            };
            
        } catch (error) {
            console.error('Error casting vote with reason:', error);
            throw error;
        }
    }
    
    /**
     * Create proposal on-chain
     * @param {array} targets - Target addresses
     * @param {array} values - ETH values
     * @param {array} calldatas - Encoded function calls
     * @param {string} description - Proposal description
     * @returns {Promise<object>} Transaction receipt with proposal ID
     */
    async createProposalOnChain(targets, values, calldatas, description) {
        if (!this.contracts.governor) {
            throw new Error('Governor contract not initialized');
        }
        
        try {
            // console.log('Creating proposal on-chain:', { targets, values, calldatas, description });
            
            // Send transaction
            const tx = await this.contracts.governor.propose(targets, values, calldatas, description);
            // console.log('Proposal transaction sent:', tx.hash);
            
            // Wait for confirmation
            const receipt = await tx.wait();
            // console.log('Proposal created:', receipt);
            
            // Extract proposal ID from event
            const event = receipt.events.find(e => e.event === 'ProposalCreated');
            const proposalId = event ? event.args.proposalId.toString() : null;
            
            return {
                success: true,
                txHash: tx.hash,
                blockNumber: receipt.blockNumber,
                proposalId: proposalId
            };
            
        } catch (error) {
            console.error('Error creating proposal on-chain:', error);
            throw error;
        }
    }
    
    /**
     * Estimate gas for transaction
     * @param {string} method - Contract method name
     * @param {array} params - Method parameters
     * @returns {Promise<string>} Gas estimate
     */
    async estimateGas(method, params) {
        try {
            let contract;
            
            // Determine which contract
            if (['delegate'].includes(method)) {
                contract = this.contracts.token;
            } else if (['castVote', 'propose'].includes(method)) {
                contract = this.contracts.governor;
            } else {
                throw new Error(`Unknown method: ${method}`);
            }
            
            if (!contract) {
                throw new Error('Contract not initialized');
            }
            
            // Estimate gas
            const gasEstimate = await contract.estimateGas[method](...params);
            return gasEstimate.toString();
            
        } catch (error) {
            console.error('Error estimating gas:', error);
            throw error;
        }
    }
    
    /**
     * Get current gas price
     * @returns {Promise<string>} Gas price in gwei
     */
    async getGasPrice() {
        if (!this.provider) {
            throw new Error('Provider not initialized');
        }
        
        try {
            const gasPrice = await this.provider.getGasPrice();
            return ethers.utils.formatUnits(gasPrice, 'gwei');
        } catch (error) {
            console.error('Error getting gas price:', error);
            throw error;
        }
    }
}

// Export as singleton
window.Web3Contracts = Web3Contracts;
window.web3Contracts = new Web3Contracts();

// console.log('Web3Contracts module loaded');
