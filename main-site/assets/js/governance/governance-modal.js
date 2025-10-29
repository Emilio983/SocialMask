/**
 * ============================================
 * GOVERNANCE MODALS MODULE
 * ============================================
 * Handles all modal dialogs (proposal detail, create, delegate)
 */

class GovernanceModals {
    constructor() {
        this.detailModal = document.getElementById('proposalDetailModal');
        this.createModal = document.getElementById('createProposalModal');
        this.delegateModal = document.getElementById('delegateModal');
    }
    
    /**
     * Open proposal detail modal
     */
    async openProposalDetail(proposalId) {
        if (!this.detailModal) return;
        
        this.detailModal.classList.remove('hidden');
        this.detailModal.innerHTML = `
            <div class="bg-brand-bg-secondary rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-8">
                <div class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-purple-600 mb-4"></i>
                    <p class="text-brand-text-secondary">Loading proposal details...</p>
                </div>
            </div>
        `;
        
        try {
            const data = await window.GovernanceAPI.getProposal(proposalId);
            this.renderProposalDetail(data.proposal);
        } catch (error) {
            console.error('Error loading proposal detail:', error);
            this.showDetailError();
        }
    }
    
    /**
     * Render proposal detail
     */
    renderProposalDetail(proposal) {
        const config = window.__SPHERA_GOVERNANCE__;
        const canVote = config.hasWallet && proposal.status === 'active';
        
        const statusColors = {
            pending: 'bg-yellow-100 text-yellow-800',
            active: 'bg-green-100 text-green-800',
            succeeded: 'bg-blue-100 text-blue-800',
            defeated: 'bg-red-900 bg-opacity-20 text-red-300',
            queued: 'bg-purple-100 text-purple-800',
            executed: 'bg-brand-bg-secondary text-brand-text-primary'
        };
        
        this.detailModal.innerHTML = `
            <div class="bg-brand-bg-secondary rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto custom-scrollbar">
                <!-- Header -->
                <div class="sticky top-0 bg-brand-bg-secondary border-b border-brand-border p-6 flex items-center justify-between z-10">
                    <h2 class="text-2xl font-bold text-gray-900">Proposal Details</h2>
                    <button onclick="window.GovernanceModals.closeProposalDetail()" class="text-gray-400 hover:text-brand-text-secondary">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Status and Category -->
                    <div class="flex items-center gap-3">
                        <span class="status-badge px-4 py-2 rounded-full ${statusColors[proposal.status] || 'bg-brand-bg-secondary text-brand-text-primary'}">
                            ${proposal.status}
                        </span>
                        <span class="text-sm text-brand-text-secondary px-3 py-1 bg-brand-bg-secondary rounded-full">
                            ${proposal.category.replace('_', ' ')}
                        </span>
                        ${proposal.quorum_reached ? 
                            '<span class="text-sm text-green-600 px-3 py-1 bg-green-50 rounded-full"><i class="fas fa-check-circle mr-1"></i>Quorum Reached</span>' : 
                            ''
                        }
                    </div>
                    
                    <!-- Title -->
                    <div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2">${this.escapeHtml(proposal.title)}</h3>
                        <div class="text-sm text-gray-500">
                            Proposed by <span class="font-mono">${this.truncateAddress(proposal.proposer_wallet)}</span>
                            ${proposal.created_at ? ` on ${new Date(proposal.created_at).toLocaleDateString()}` : ''}
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="prose max-w-none">
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Description</h4>
                        <div class="text-gray-700 whitespace-pre-wrap">${this.escapeHtml(proposal.description)}</div>
                    </div>
                    
                    <!-- Voting Results -->
                    ${proposal.status !== 'pending' ? `
                        <div class="bg-brand-bg-primary rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Voting Results</h4>
                            
                            <div class="space-y-4">
                                <!-- For -->
                                <div>
                                    <div class="flex justify-between text-sm mb-2">
                                        <span class="font-semibold text-green-600">
                                            <i class="fas fa-thumbs-up mr-1"></i> For
                                        </span>
                                        <span class="text-gray-700">${proposal.votes_for_formatted || '0'}</span>
                                    </div>
                                    <div class="w-full bg-brand-border rounded-full h-3">
                                        <div class="bg-green-500 h-3 rounded-full" style="width: ${proposal.for_percentage || 0}%"></div>
                                    </div>
                                </div>
                                
                                <!-- Against -->
                                <div>
                                    <div class="flex justify-between text-sm mb-2">
                                        <span class="font-semibold text-red-300">
                                            <i class="fas fa-thumbs-down mr-1"></i> Against
                                        </span>
                                        <span class="text-gray-700">${proposal.votes_against_formatted || '0'}</span>
                                    </div>
                                    <div class="w-full bg-brand-border rounded-full h-3">
                                        <div class="bg-red-900 bg-opacity-200 h-3 rounded-full" style="width: ${proposal.against_percentage || 0}%"></div>
                                    </div>
                                </div>
                                
                                <!-- Abstain -->
                                <div>
                                    <div class="flex justify-between text-sm mb-2">
                                        <span class="font-semibold text-brand-text-secondary">
                                            <i class="fas fa-minus mr-1"></i> Abstain
                                        </span>
                                        <span class="text-gray-700">${proposal.votes_abstain_formatted || '0'}</span>
                                    </div>
                                    <div class="w-full bg-brand-border rounded-full h-3">
                                        <div class="bg-brand-text-secondary h-3 rounded-full" style="width: ${proposal.abstain_percentage || 0}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Timeline -->
                    ${proposal.voting_starts_at ? `
                        <div class="bg-blue-50 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Timeline</h4>
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-calendar-plus text-blue-500 w-6"></i>
                                    <span class="text-brand-text-secondary ml-2">Created:</span>
                                    <span class="text-gray-900 ml-2 font-semibold">${new Date(proposal.created_at).toLocaleString()}</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-play text-green-500 w-6"></i>
                                    <span class="text-brand-text-secondary ml-2">Voting Starts:</span>
                                    <span class="text-gray-900 ml-2 font-semibold">${new Date(proposal.voting_starts_at).toLocaleString()}</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-stop text-red-400 w-6"></i>
                                    <span class="text-brand-text-secondary ml-2">Voting Ends:</span>
                                    <span class="text-gray-900 ml-2 font-semibold">${new Date(proposal.voting_ends_at).toLocaleString()}</span>
                                </div>
                                ${proposal.time_remaining ? `
                                    <div class="flex items-center text-sm">
                                        <i class="fas fa-hourglass-half text-orange-500 w-6"></i>
                                        <span class="text-brand-text-secondary ml-2">Time Remaining:</span>
                                        <span class="text-gray-900 ml-2 font-semibold">${proposal.time_remaining}</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Actions -->
                    ${proposal.actions && proposal.actions.length > 0 ? `
                        <div class="bg-purple-50 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Proposed Actions (${proposal.actions.length})</h4>
                            <div class="space-y-3">
                                ${proposal.actions.map((action, i) => `
                                    <div class="bg-brand-bg-secondary rounded p-3 text-sm">
                                        <div class="font-semibold text-gray-700 mb-1">Action ${i + 1}</div>
                                        <div class="text-brand-text-secondary">Target: <span class="font-mono text-xs">${action.target}</span></div>
                                        <div class="text-brand-text-secondary">Value: ${action.value} ETH</div>
                                        ${action.decoded?.function ? `<div class="text-brand-text-secondary">Function: ${action.decoded.function}</div>` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Voting Buttons -->
                    ${canVote ? `
                        <div class="flex gap-3 pt-4">
                            <button onclick="window.GovernanceModals.castVote('${proposal.proposal_id}', 1)" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-semibold transition">
                                <i class="fas fa-thumbs-up mr-2"></i>
                                Vote For
                            </button>
                            <button onclick="window.GovernanceModals.castVote('${proposal.proposal_id}', 0)" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-semibold transition">
                                <i class="fas fa-thumbs-down mr-2"></i>
                                Vote Against
                            </button>
                            <button onclick="window.GovernanceModals.castVote('${proposal.proposal_id}', 2)" class="flex-1 bg-brand-bg-secondary hover:bg-opacity-80 text-white py-3 rounded-lg font-semibold transition">
                                <i class="fas fa-minus mr-2"></i>
                                Abstain
                            </button>
                        </div>
                    ` : !config.hasWallet ? `
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                            <i class="fas fa-wallet text-yellow-600 text-2xl mb-2"></i>
                            <p class="text-yellow-800">Connect your wallet to vote on this proposal</p>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    /**
     * Close proposal detail modal
     */
    closeProposalDetail() {
        if (this.detailModal) {
            this.detailModal.classList.add('hidden');
        }
    }
    
    /**
     * Cast vote
     */
    async castVote(proposalId, voteType) {
        const voteNames = ['Against', 'For', 'Abstain'];
        
        if (!confirm(`Confirm your vote: ${voteNames[voteType]}?`)) {
            return;
        }
        
        try {
            const result = await window.GovernanceAPI.castVote(proposalId, voteType);
            alert(result.message || 'Vote cast successfully!');
            this.closeProposalDetail();
            window.GovernanceProposals.loadProposals(window.GovernanceProposals.currentFilters, window.GovernanceProposals.currentPage);
        } catch (error) {
            alert('Error casting vote: ' + error.message);
        }
    }
    
    /**
     * Show detail error
     */
    showDetailError() {
        if (!this.detailModal) return;
        
        this.detailModal.innerHTML = `
            <div class="bg-brand-bg-secondary rounded-lg max-w-4xl w-full p-8">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-red-300 mb-2">Failed to Load Proposal</h3>
                    <p class="text-red-300 mb-4">Please try again later</p>
                    <button onclick="window.GovernanceModals.closeProposalDetail()" class="bg-brand-bg-secondary text-white px-6 py-2 rounded-lg hover:bg-opacity-80">
                        Close
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * Open delegate modal
     */
    openDelegateModal() {
        if (!this.delegateModal) return;
        
        this.delegateModal.classList.remove('hidden');
        this.delegateModal.innerHTML = `
            <div class="bg-brand-bg-secondary rounded-lg max-w-md w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Delegate Voting Power</h3>
                    <button onclick="window.GovernanceModals.closeDelegateModal()" class="text-gray-400 hover:text-brand-text-secondary">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <p class="text-brand-text-secondary text-sm mb-4">
                    Delegate your voting power to another address or yourself to participate in governance.
                </p>
                
                <form onsubmit="window.GovernanceModals.submitDelegate(event)" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Delegatee Address
                        </label>
                        <input 
                            type="text" 
                            id="delegateeAddress"
                            placeholder="0x..."
                            required
                            pattern="^0x[a-fA-F0-9]{40}$"
                            class="w-full border border-brand-border rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">Enter an Ethereum address</p>
                    </div>
                    
                    <button type="button" onclick="document.getElementById('delegateeAddress').value = '${window.__SPHERA_GOVERNANCE__.userWallet}'" class="text-sm text-purple-600 hover:text-purple-700">
                        <i class="fas fa-user mr-1"></i>
                        Delegate to myself
                    </button>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="window.GovernanceModals.closeDelegateModal()" class="flex-1 bg-brand-bg-primary hover:bg-brand-bg-secondary text-brand-text-primary py-2 rounded-lg font-semibold">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg font-semibold">
                            Delegate
                        </button>
                    </div>
                </form>
            </div>
        `;
    }
    
    /**
     * Close delegate modal
     */
    closeDelegateModal() {
        if (this.delegateModal) {
            this.delegateModal.classList.add('hidden');
        }
    }
    
    /**
     * Submit delegation
     */
    async submitDelegate(event) {
        event.preventDefault();
        
        const delegatee = document.getElementById('delegateeAddress').value;
        
        if (!delegatee || !delegatee.match(/^0x[a-fA-F0-9]{40}$/)) {
            alert('Please enter a valid Ethereum address');
            return;
        }
        
        try {
            const result = await window.GovernanceAPI.delegate(delegatee);
            alert(result.message || 'Delegation successful!');
            this.closeDelegateModal();
            
            // Reload voting power
            if (window.__SPHERA_GOVERNANCE__.userWallet) {
                window.GovernanceStats.loadVotingPower(window.__SPHERA_GOVERNANCE__.userWallet);
            }
        } catch (error) {
            alert('Error delegating: ' + error.message);
        }
    }
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Truncate address
     */
    truncateAddress(address) {
        if (!address) return 'N/A';
        return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
    }
}

// Export as global
window.GovernanceModals = new GovernanceModals();
