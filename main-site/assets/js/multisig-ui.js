/**
 * ============================================
 * MULTI-SIGNATURE UI CONTROLLER
 * ============================================
 * Handles multi-sig proposal creation and signing
 */

class MultiSigUI {
    constructor() {
        this.apiBase = '/api/governance';
        this.contractAddress = null;
        this.userAddress = null;
        this.requiredSignatures = 3;
        this.totalSigners = 5;
    }

    /**
     * Initialize the multi-sig system
     */
    async init(contractAddress) {
        this.contractAddress = contractAddress;
        
        if (typeof window.smartWalletProvider !== 'undefined') {
            try {
                const accounts = await window.smartWalletProvider.request({ 
                    method: 'eth_requestAccounts' 
                });
                this.userAddress = accounts[0];
                
                console.log('✅ MultiSig initialized:', {
                    contract: this.contractAddress,
                    user: this.userAddress
                });
                
                await this.loadPendingProposals();
                
            } catch (error) {
                console.error('❌ Failed to connect wallet:', error);
            }
        }
    }

    /**
     * Create a new multi-sig proposal
     */
    async createProposal(data) {
        try {
            if (!this.userAddress) {
                throw new Error('Wallet not connected');
            }

            // Validate data
            if (!data.proposalType || !data.title || !data.description || !data.targetContract) {
                throw new Error('Missing required fields');
            }

            this.showLoading('Creating proposal...');

            const payload = {
                proposalId: data.proposalId || Date.now(),
                proposalType: data.proposalType,
                title: data.title,
                description: data.description,
                proposerAddress: this.userAddress,
                targetContract: data.targetContract,
                functionData: data.functionData || '',
                ethValue: data.ethValue || '0',
                durationDays: data.durationDays || 7
            };

            const response = await fetch(`${this.apiBase}/multisig-create.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to create proposal');
            }

            this.hideLoading();
            this.showSuccess(`Proposal #${result.proposalId} created! Requires ${this.requiredSignatures} signatures.`);
            
            setTimeout(() => this.loadPendingProposals(), 1000);
            
            return result;

        } catch (error) {
            this.hideLoading();
            this.showError(error.message);
            throw error;
        }
    }

    /**
     * Sign a proposal
     */
    async signProposal(proposalId) {
        try {
            if (!this.userAddress) {
                throw new Error('Wallet not connected');
            }

            this.showLoading('Please sign the message...');

            // Create message to sign
            const message = this.createSignatureMessage(proposalId);
            
            // Request signature from user
            const signature = await window.smartWalletProvider.request({
                method: 'personal_sign',
                params: [message, this.userAddress]
            });

            // Calculate message hash
            const messageHash = await this.calculateMessageHash(message);

            // Submit signature
            const response = await fetch(`${this.apiBase}/multisig-sign.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    proposalId: proposalId,
                    signerAddress: this.userAddress,
                    signature: signature,
                    messageHash: messageHash
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to add signature');
            }

            this.hideLoading();
            
            if (result.approved) {
                this.showSuccess(`✅ Proposal approved! (${result.signatureCount}/${result.requiredSignatures} signatures)`);
            } else {
                this.showSuccess(`Signature added (${result.signatureCount}/${result.requiredSignatures})`);
            }
            
            setTimeout(() => this.loadPendingProposals(), 1000);
            
            return result;

        } catch (error) {
            this.hideLoading();
            
            if (error.code === 4001) {
                this.showError('Signature rejected by user');
            } else {
                this.showError(error.message);
            }
            
            throw error;
        }
    }

    /**
     * Load pending proposals
     */
    async loadPendingProposals() {
        try {
            const response = await fetch(`${this.apiBase}/multisig-list.php?status=PENDING`);
            const result = await response.json();

            if (result.success && result.proposals) {
                this.renderProposals(result.proposals);
            }

        } catch (error) {
            console.error('Failed to load proposals:', error);
        }
    }

    /**
     * Render proposals list
     */
    renderProposals(proposals) {
        const container = document.getElementById('multisig-proposals');
        if (!container) return;

        if (proposals.length === 0) {
            container.innerHTML = `
                <div class="no-proposals">
                    <i class="fas fa-clipboard-check"></i>
                    <p>No pending multi-sig proposals</p>
                </div>
            `;
            return;
        }

        container.innerHTML = proposals.map(proposal => `
            <div class="multisig-proposal" data-id="${proposal.proposal_id}">
                <div class="proposal-header">
                    <span class="proposal-type ${proposal.proposal_type.toLowerCase()}">${this.formatProposalType(proposal.proposal_type)}</span>
                    <span class="proposal-id">#${proposal.proposal_id}</span>
                </div>
                
                <h3>${proposal.title}</h3>
                <p class="description">${proposal.description}</p>
                
                <div class="proposal-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span>${this.formatAddress(proposal.proposer_address)}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span>${this.formatTimeRemaining(proposal.expires_at)}</span>
                    </div>
                </div>
                
                <div class="signature-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${(proposal.signature_count / this.requiredSignatures) * 100}%"></div>
                    </div>
                    <span class="progress-text">${proposal.signature_count} / ${this.requiredSignatures} signatures</span>
                </div>
                
                <div class="proposal-actions">
                    <button onclick="multiSigUI.signProposal(${proposal.proposal_id})" 
                            class="btn-sign"
                            ${proposal.has_signed ? 'disabled' : ''}>
                        ${proposal.has_signed ? '✓ Signed' : 'Sign Proposal'}
                    </button>
                    <button onclick="multiSigUI.viewDetails(${proposal.proposal_id})" 
                            class="btn-details">
                        View Details
                    </button>
                </div>
            </div>
        `).join('');
    }

    /**
     * Create signature message
     */
    createSignatureMessage(proposalId) {
        return `I approve multi-sig proposal #${proposalId}\n\nContract: ${this.contractAddress}\nSigner: ${this.userAddress}\nTimestamp: ${Date.now()}`;
    }

    /**
     * Calculate message hash
     */
    async calculateMessageHash(message) {
        const msgBuffer = new TextEncoder().encode(message);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return '0x' + hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Format proposal type
     */
    formatProposalType(type) {
        const types = {
            'TREASURY_WITHDRAWAL': '💰 Treasury',
            'PARAMETER_CHANGE': '⚙️ Parameters',
            'SIGNER_CHANGE': '👥 Signers',
            'EMERGENCY_ACTION': '🚨 Emergency',
            'CONTRACT_UPGRADE': '⬆️ Upgrade'
        };
        return types[type] || type;
    }

    /**
     * Format address
     */
    formatAddress(address) {
        return `${address.substring(0, 6)}...${address.substring(38)}`;
    }

    /**
     * Format time remaining
     */
    formatTimeRemaining(expiresAt) {
        const now = new Date();
        const expires = new Date(expiresAt);
        const diff = expires - now;
        
        if (diff < 0) return 'Expired';
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        
        if (days > 0) return `${days}d ${hours}h remaining`;
        return `${hours}h remaining`;
    }

    /**
     * Show loading indicator
     */
    showLoading(message) {
        const loader = document.getElementById('multisig-loader');
        if (loader) {
            loader.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${message}`;
            loader.style.display = 'flex';
        }
    }

    /**
     * Hide loading indicator
     */
    hideLoading() {
        const loader = document.getElementById('multisig-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    /**
     * Show error message
     */
    showError(message) {
        this.showNotification(message, 'error');
    }

    /**
     * Show notification
     */
    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 100);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}

// Global instance
const multiSigUI = new MultiSigUI();

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Auto-initialize if contract address is available
    const contractAddress = document.querySelector('[data-multisig-contract]')?.dataset.multisigContract;
    if (contractAddress) {
        multiSigUI.init(contractAddress);
    }
});
