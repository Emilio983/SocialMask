/**
 * ============================================
 * EIP-712 SIGNER
 * ============================================
 * Sign votes using EIP-712 standard for gasless voting
 */

class EIP712Signer {
    constructor(contractAddress, chainId = 1) {
        this.contractAddress = contractAddress;
        this.chainId = chainId;
        
        // EIP-712 Domain
        this.domain = {
            name: 'Sphera Governance',
            version: '1',
            chainId: chainId,
            verifyingContract: contractAddress
        };
        
        // Vote Type
        this.types = {
            Vote: [
                { name: 'proposalId', type: 'uint256' },
                { name: 'support', type: 'uint8' },
                { name: 'voter', type: 'address' },
                { name: 'nonce', type: 'uint256' },
                { name: 'deadline', type: 'uint256' }
            ]
        };
    }

    /**
     * Sign a vote using EIP-712
     * @param {Object} vote - Vote data
     * @returns {Promise<string>} Signature
     */
    async signVote(vote) {
        try {
            if (typeof window.smartWalletProvider === 'undefined') {
                throw new Error('Smart Wallet not detected');
            }

            const accounts = await window.smartWalletProvider.request({ 
                method: 'eth_requestAccounts' 
            });
            
            const voter = accounts[0];

            // Get current nonce
            const nonce = await this.getNonce(voter);

            // Set deadline (1 hour from now)
            const deadline = Math.floor(Date.now() / 1000) + 3600;

            // Prepare typed data
            const typedData = {
                types: {
                    EIP712Domain: [
                        { name: 'name', type: 'string' },
                        { name: 'version', type: 'string' },
                        { name: 'chainId', type: 'uint256' },
                        { name: 'verifyingContract', type: 'address' }
                    ],
                    Vote: this.types.Vote
                },
                primaryType: 'Vote',
                domain: this.domain,
                message: {
                    proposalId: vote.proposalId,
                    support: vote.support,
                    voter: voter,
                    nonce: nonce,
                    deadline: deadline
                }
            };

            console.log('📝 Signing vote with EIP-712:', typedData);

            // Sign using eth_signTypedData_v4
            const signature = await window.smartWalletProvider.request({
                method: 'eth_signTypedData_v4',
                params: [voter, JSON.stringify(typedData)]
            });

            console.log('✅ Signature created:', signature);

            return {
                signature,
                voter,
                nonce,
                deadline,
                proposalId: vote.proposalId,
                support: vote.support
            };

        } catch (error) {
            console.error('❌ Signing error:', error);
            throw error;
        }
    }

    /**
     * Get current nonce for voter
     * @param {string} voter - Voter address
     * @returns {Promise<number>} Current nonce
     */
    async getNonce(voter) {
        try {
            // Get nonce from backend
            const response = await fetch(`/api/governance/get-nonce.php?address=${voter}`);
            const data = await response.json();
            
            if (data.success) {
                return data.nonce;
            }
            
            return 0; // Default nonce
        } catch (error) {
            console.error('Error fetching nonce:', error);
            return 0;
        }
    }

    /**
     * Verify a signature (client-side check)
     * @param {Object} signedVote - Signed vote data
     * @returns {boolean} Is valid
     */
    async verifySignature(signedVote) {
        try {
            const { signature, voter, nonce, deadline, proposalId, support } = signedVote;

            // Reconstruct typed data
            const typedData = {
                types: {
                    EIP712Domain: [
                        { name: 'name', type: 'string' },
                        { name: 'version', type: 'string' },
                        { name: 'chainId', type: 'uint256' },
                        { name: 'verifyingContract', type: 'address' }
                    ],
                    Vote: this.types.Vote
                },
                primaryType: 'Vote',
                domain: this.domain,
                message: {
                    proposalId,
                    support,
                    voter,
                    nonce,
                    deadline
                }
            };

            // Calculate hash
            const hash = ethers.utils._TypedDataEncoder.hash(
                typedData.domain,
                { Vote: typedData.types.Vote },
                typedData.message
            );

            // Recover signer
            const recoveredAddress = ethers.utils.recoverAddress(hash, signature);

            return recoveredAddress.toLowerCase() === voter.toLowerCase();

        } catch (error) {
            console.error('Verification error:', error);
            return false;
        }
    }

    /**
     * Display human-readable vote details before signing
     * @param {Object} vote - Vote data
     * @returns {string} HTML formatted message
     */
    formatVoteMessage(vote) {
        const supportText = ['Against', 'For', 'Abstain'][vote.support];
        const supportEmoji = ['❌', '✅', '⚪'][vote.support];

        return `
            <div class="eip712-message">
                <h3>🗳️ Sign Your Vote</h3>
                <div class="vote-details">
                    <div class="detail-row">
                        <span class="label">Proposal:</span>
                        <span class="value">${vote.proposalId}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Your Vote:</span>
                        <span class="value ${supportText.toLowerCase()}">
                            ${supportEmoji} ${supportText}
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Gas Cost:</span>
                        <span class="value free">FREE (No gas required)</span>
                    </div>
                </div>
                <p class="info">
                    <i class="fas fa-info-circle"></i>
                    You're signing this vote off-chain. Our relayer will submit it to the blockchain for you.
                </p>
            </div>
        `;
    }
}

// Export
window.EIP712Signer = EIP712Signer;

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
    // Contract address and chain ID will be loaded from config
    const contractAddress = '0x...'; // TODO: Load from config
    const chainId = 1; // TODO: Load from config
    
    window.eip712Signer = new EIP712Signer(contractAddress, chainId);
    console.log('✅ EIP-712 Signer initialized');
});
