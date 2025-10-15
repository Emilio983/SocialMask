/**
 * ============================================
 * WEB3 SIGNATURES
 * ============================================
 * Firmado de mensajes y datos estructurados (EIP-712)
 * 
 * Requires:
 * - Ethers.js (loaded via CDN)
 * - Web3Connector
 * 
 * @author GitHub Copilot
 * @version 1.0.0
 * @date 2025-10-08
 */

class Web3Signatures {
    constructor() {
        this.provider = null;
        this.signer = null;
        
        // EIP-712 Domain for Sphera Governance
        this.domain = {
            name: 'Sphera Governance',
            version: '1',
            chainId: null, // Will be set dynamically
            verifyingContract: null // Will be set to Governor address
        };
    }
    
    /**
     * Initialize with provider
     * @returns {Promise<void>}
     */
    async initialize() {
        try {
            if (!window.smartWalletProvider) {
                throw new Error('Smart Wallet not found');
            }
            
            this.provider = new ethers.providers.Web3Provider(window.smartWalletProvider);
            this.signer = this.provider.getSigner();
            
            // Get chain ID
            const network = await this.provider.getNetwork();
            this.domain.chainId = network.chainId;
            
            // Set verifying contract
            const config = window.__SPHERA_GOVERNANCE__;
            if (config && config.contracts && config.contracts.governor) {
                this.domain.verifyingContract = config.contracts.governor;
            }
            
            // console.log('Web3Signatures initialized with domain:', this.domain);
            
        } catch (error) {
            console.error('Error initializing Web3Signatures:', error);
            throw error;
        }
    }
    
    /**
     * Sign a simple message (personal_sign)
     * @param {string} message - Message to sign
     * @returns {Promise<string>} Signature
     */
    async signMessage(message) {
        if (!this.signer) {
            await this.initialize();
        }
        
        try {
            const signature = await this.signer.signMessage(message);
            // console.log('Message signed:', signature);
            return signature;
        } catch (error) {
            console.error('Error signing message:', error);
            throw error;
        }
    }
    
    /**
     * Sign typed data (EIP-712)
     * @param {object} domain - EIP-712 domain
     * @param {object} types - EIP-712 types
     * @param {object} value - Data to sign
     * @returns {Promise<string>} Signature
     */
    async signTypedData(domain, types, value) {
        if (!this.signer) {
            await this.initialize();
        }
        
        try {
            const signature = await this.signer._signTypedData(domain, types, value);
            // console.log('Typed data signed:', signature);
            return signature;
        } catch (error) {
            console.error('Error signing typed data:', error);
            throw error;
        }
    }
    
    /**
     * Generate vote signature message (EIP-712)
     * @param {number} proposalId - Proposal ID
     * @param {number} support - Vote support (0=against, 1=for, 2=abstain)
     * @param {string} voter - Voter address
     * @param {string} reason - Optional vote reason
     * @returns {Promise<object>} Signature data
     */
    async signVote(proposalId, support, voter, reason = '') {
        if (!this.signer) {
            await this.initialize();
        }
        
        try {
            // EIP-712 types for vote
            const types = {
                Vote: [
                    { name: 'proposalId', type: 'uint256' },
                    { name: 'support', type: 'uint8' },
                    { name: 'voter', type: 'address' },
                    { name: 'reason', type: 'string' },
                    { name: 'nonce', type: 'uint256' },
                    { name: 'timestamp', type: 'uint256' }
                ]
            };
            
            // Generate nonce
            const nonce = Date.now();
            const timestamp = Math.floor(Date.now() / 1000);
            
            // Value to sign
            const value = {
                proposalId: proposalId.toString(),
                support: support,
                voter: voter,
                reason: reason || '',
                nonce: nonce,
                timestamp: timestamp
            };
            
            // Sign
            const signature = await this.signTypedData(this.domain, types, value);
            
            return {
                proposalId: proposalId.toString(),
                support: support,
                voter: voter,
                reason: reason,
                nonce: nonce,
                timestamp: timestamp,
                signature: signature
            };
            
        } catch (error) {
            console.error('Error signing vote:', error);
            throw error;
        }
    }
    
    /**
     * Generate delegation signature (EIP-712)
     * @param {string} delegator - Delegator address
     * @param {string} delegatee - Delegatee address
     * @returns {Promise<object>} Signature data
     */
    async signDelegation(delegator, delegatee) {
        if (!this.signer) {
            await this.initialize();
        }
        
        try {
            // EIP-712 types for delegation
            const types = {
                Delegation: [
                    { name: 'delegator', type: 'address' },
                    { name: 'delegatee', type: 'address' },
                    { name: 'nonce', type: 'uint256' },
                    { name: 'timestamp', type: 'uint256' }
                ]
            };
            
            // Generate nonce
            const nonce = Date.now();
            const timestamp = Math.floor(Date.now() / 1000);
            
            // Value to sign
            const value = {
                delegator: delegator,
                delegatee: delegatee,
                nonce: nonce,
                timestamp: timestamp
            };
            
            // Sign
            const signature = await this.signTypedData(this.domain, types, value);
            
            return {
                delegator: delegator,
                delegatee: delegatee,
                nonce: nonce,
                timestamp: timestamp,
                signature: signature
            };
            
        } catch (error) {
            console.error('Error signing delegation:', error);
            throw error;
        }
    }
    
    /**
     * Generate wallet verification signature
     * @param {string} address - Wallet address
     * @returns {Promise<object>} Signature data
     */
    async signWalletVerification(address) {
        if (!this.signer) {
            await this.initialize();
        }
        
        try {
            const timestamp = Math.floor(Date.now() / 1000);
            const message = `Verify wallet ownership for Sphera Governance\n\nAddress: ${address}\nTimestamp: ${timestamp}`;
            
            const signature = await this.signMessage(message);
            
            return {
                address: address,
                message: message,
                timestamp: timestamp,
                signature: signature
            };
            
        } catch (error) {
            console.error('Error signing wallet verification:', error);
            throw error;
        }
    }
    
    /**
     * Recover signer from signature (frontend verification)
     * @param {string} message - Original message
     * @param {string} signature - Signature to verify
     * @returns {string} Recovered address
     */
    recoverSigner(message, signature) {
        try {
            const recovered = ethers.utils.verifyMessage(message, signature);
            return recovered;
        } catch (error) {
            console.error('Error recovering signer:', error);
            throw error;
        }
    }
    
    /**
     * Recover signer from typed data (EIP-712)
     * @param {object} domain - EIP-712 domain
     * @param {object} types - EIP-712 types
     * @param {object} value - Signed data
     * @param {string} signature - Signature
     * @returns {string} Recovered address
     */
    recoverTypedDataSigner(domain, types, value, signature) {
        try {
            const recovered = ethers.utils.verifyTypedData(domain, types, value, signature);
            return recovered;
        } catch (error) {
            console.error('Error recovering typed data signer:', error);
            throw error;
        }
    }
    
    /**
     * Verify signature matches expected signer
     * @param {string} message - Original message
     * @param {string} signature - Signature
     * @param {string} expectedSigner - Expected signer address
     * @returns {boolean} True if signature is valid
     */
    verifySignature(message, signature, expectedSigner) {
        try {
            const recovered = this.recoverSigner(message, signature);
            return recovered.toLowerCase() === expectedSigner.toLowerCase();
        } catch (error) {
            console.error('Error verifying signature:', error);
            return false;
        }
    }
}

// Export as singleton
window.Web3Signatures = Web3Signatures;
window.web3Signatures = new Web3Signatures();

// console.log('Web3Signatures module loaded');
