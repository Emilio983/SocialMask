/**
 * ============================================
 * GASLESS VOTING UI
 * ============================================
 * Main controller for gasless voting interface
 */

class GaslessVotingUI {
    constructor() {
        this.apiBase = '/api/governance';
        this.signer = null;
    }

    /**
     * Initialize gasless voting
     */
    async init() {
        try {
            // Get contract address from config
            const config = await this.getConfig();
            
            if (!config.contract_address) {
                console.warn('⚠️ Gasless voting contract not deployed yet');
                return false;
            }

            // Initialize EIP-712 signer
            this.signer = new EIP712Signer(
                config.contract_address,
                parseInt(config.chain_id)
            );

            console.log('✅ Gasless voting initialized');
            return true;

        } catch (error) {
            console.error('Initialization error:', error);
            return false;
        }
    }

    /**
     * Cast gasless vote
     * @param {string} proposalId - Proposal ID
     * @param {number} support - 0=Against, 1=For, 2=Abstain
     */
    async castGaslessVote(proposalId, support) {
        try {
            if (!this.signer) {
                await this.init();
            }

            this.showLoading('Preparing vote signature...');

            // Show vote details before signing
            const message = this.signer.formatVoteMessage({ proposalId, support });
            this.showVotePreview(message);

            // Sign vote with EIP-712
            const signedVote = await this.signer.signVote({
                proposalId,
                support
            });

            this.showLoading('Submitting vote to relayer...');

            // Submit to relayer
            const response = await fetch(`${this.apiBase}/gasless-vote.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(signedVote)
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(`
                    ✅ Vote submitted successfully!<br>
                    <strong>Gas Saved:</strong> ${data.data.gas_saved}<br>
                    <strong>TX Hash:</strong> <a href="https://etherscan.io/tx/${data.data.tx_hash}" target="_blank">${data.data.tx_hash.slice(0, 10)}...</a>
                `);
                
                // Reload proposal data
                setTimeout(() => {
                    window.location.reload();
                }, 3000);

                return data.data;
            } else {
                throw new Error(data.error || 'Failed to submit vote');
            }

        } catch (error) {
            console.error('Vote error:', error);
            
            if (error.code === 4001) {
                this.showError('Signature rejected by user');
            } else {
                this.showError('Failed to cast vote: ' + error.message);
            }
            
            return null;
        }
    }

    /**
     * Get relayer configuration
     */
    async getConfig() {
        try {
            const response = await fetch(`${this.apiBase}/gasless-config.php`);
            const data = await response.json();
            
            if (data.success) {
                return data.config;
            }
            
            return {};
        } catch (error) {
            console.error('Config error:', error);
            return {};
        }
    }

    /**
     * Get user's voting power
     */
    async getVotingPower(address) {
        try {
            const response = await fetch(`${this.apiBase}/voting-power.php?address=${address}`);
            const data = await response.json();
            
            if (data.success) {
                return data.votingPower;
            }
            
            return 0;
        } catch (error) {
            console.error('Voting power error:', error);
            return 0;
        }
    }

    /**
     * Check if user has already voted
     */
    async hasVoted(proposalId, address) {
        try {
            const response = await fetch(
                `${this.apiBase}/has-voted.php?proposal_id=${proposalId}&address=${address}`
            );
            const data = await response.json();
            
            return data.hasVoted || false;
        } catch (error) {
            console.error('Has voted check error:', error);
            return false;
        }
    }

    /**
     * Get gasless voting statistics
     */
    async getStats() {
        try {
            const response = await fetch(`${this.apiBase}/gasless-stats.php`);
            const data = await response.json();
            
            if (data.success) {
                return data.stats;
            }
            
            return null;
        } catch (error) {
            console.error('Stats error:', error);
            return null;
        }
    }

    /**
     * Show vote preview modal
     */
    showVotePreview(message) {
        // Create modal if it doesn't exist
        let modal = document.getElementById('vote-preview-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'vote-preview-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <div id="vote-preview-content"></div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close button
            modal.querySelector('.close').onclick = () => {
                modal.style.display = 'none';
            };
        }

        // Set content
        document.getElementById('vote-preview-content').innerHTML = message;
        modal.style.display = 'block';

        // Auto-hide after signing
        setTimeout(() => {
            modal.style.display = 'none';
        }, 5000);
    }

    /**
     * Show loading state
     */
    showLoading(message) {
        // TODO: Implement loading UI
        console.log('Loading:', message);
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        // TODO: Implement success UI
        console.log('Success:', message);
        
        // Simple alert for now
        const div = document.createElement('div');
        div.className = 'alert alert-success';
        div.innerHTML = message;
        div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 20px; background: #10b981; color: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
        document.body.appendChild(div);
        
        setTimeout(() => div.remove(), 5000);
    }

    /**
     * Show error message
     */
    showError(message) {
        // TODO: Implement error UI
        console.error('Error:', message);
        
        const div = document.createElement('div');
        div.className = 'alert alert-error';
        div.innerHTML = message;
        div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 20px; background: #ef4444; color: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
        document.body.appendChild(div);
        
        setTimeout(() => div.remove(), 5000);
    }
}

// Export
window.GaslessVotingUI = GaslessVotingUI;

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
    window.gaslessVotingUI = new GaslessVotingUI();
    window.gaslessVotingUI.init();
});

// Add voting buttons to proposals
function addGaslessVoteButtons(proposalId, container) {
    const buttonsHTML = `
        <div class="gasless-vote-buttons">
            <button class="btn-vote btn-vote-for" onclick="voteGasless('${proposalId}', 1)">
                <i class="fas fa-check"></i> Vote For (No Gas)
            </button>
            <button class="btn-vote btn-vote-against" onclick="voteGasless('${proposalId}', 0)">
                <i class="fas fa-times"></i> Vote Against (No Gas)
            </button>
            <button class="btn-vote btn-vote-abstain" onclick="voteGasless('${proposalId}', 2)">
                <i class="fas fa-minus"></i> Abstain (No Gas)
            </button>
        </div>
        <p class="gasless-info">
            <i class="fas fa-gift"></i> Free voting! No gas fees required.
        </p>
    `;
    
    if (container) {
        container.innerHTML = buttonsHTML;
    }
    
    return buttonsHTML;
}

// Global vote function
async function voteGasless(proposalId, support) {
    try {
        await window.gaslessVotingUI.castGaslessVote(proposalId, support);
    } catch (error) {
        console.error('Vote failed:', error);
    }
}

// Export functions
window.addGaslessVoteButtons = addGaslessVoteButtons;
window.voteGasless = voteGasless;
