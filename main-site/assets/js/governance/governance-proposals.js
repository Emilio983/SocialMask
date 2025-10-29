/**
 * ============================================
 * GOVERNANCE PROPOSALS MODULE
 * ============================================
 * Handles proposals list display and interactions
 */

class GovernanceProposals {
    constructor() {
        this.proposalsContainer = document.getElementById('proposalsList');
        this.paginationContainer = document.getElementById('pagination');
        this.currentPage = 1;
        this.currentFilters = {};
    }
    
    /**
     * Load and display proposals
     */
    async loadProposals(filters = {}, page = 1) {
        this.currentFilters = filters;
        this.currentPage = page;
        
        if (this.proposalsContainer) {
            this.showLoading();
        }
        
        try {
            const data = await window.GovernanceAPI.getProposals({
                ...filters,
                page: page,
                limit: 10
            });
            
            this.renderProposals(data.proposals || []);
            this.renderPagination(data.pagination || {});
        } catch (error) {
            console.error('Error al cargar propuestas:', error);
            this.showError();
        }
    }
    
    /**
     * Show loading state
     */
    showLoading() {
        if (!this.proposalsContainer) return;
        
        this.proposalsContainer.innerHTML = `
            <div class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl text-brand-accent mb-4"></i>
                <p class="text-brand-text-secondary">Cargando propuestas...</p>
            </div>
        `;
    }
    
    /**
     * Show error state
     */
    showError() {
        if (!this.proposalsContainer) return;
        
        this.proposalsContainer.innerHTML = `
            <div class="bg-brand-bg-secondary border border-brand-border rounded-lg p-8 text-center">
                <i class="fas fa-exclamation-triangle text-brand-accent text-4xl mb-4"></i>
                <h3 class="text-xl font-semibold text-brand-text-primary mb-2">Error al Cargar Propuestas</h3>
                <p class="text-brand-text-secondary mb-4">Por favor intenta de nuevo</p>
                <button onclick="window.GovernanceProposals.loadProposals()" class="bg-brand-accent text-white px-6 py-2 rounded-lg hover:opacity-90">
                    Reintentar
                </button>
            </div>
        `;
    }
    
    /**
     * Render proposals list
     */
    renderProposals(proposals) {
        if (!this.proposalsContainer) return;
        
        if (proposals.length === 0) {
            this.proposalsContainer.innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-4xl text-brand-text-secondary mb-4"></i>
                    <h3 class="text-xl font-semibold text-brand-text-primary mb-2">No se Encontraron Propuestas</h3>
                    <p class="text-brand-text-secondary">Intenta ajustar los filtros</p>
                </div>
            `;
            return;
        }
        
        this.proposalsContainer.innerHTML = proposals.map(proposal => 
            this.renderProposalCard(proposal)
        ).join('');
    }
    
    /**
     * Render single proposal card
     */
    renderProposalCard(proposal) {
        const statusColors = {
            pending: 'bg-yellow-100 text-yellow-800',
            active: 'bg-green-100 text-green-800',
            succeeded: 'bg-blue-100 text-blue-800',
            defeated: 'bg-red-900 bg-opacity-20 text-red-300',
            queued: 'bg-purple-100 text-purple-800',
            executed: 'bg-brand-bg-secondary text-brand-text-primary',
            canceled: 'bg-brand-bg-secondary text-brand-text-primary',
            expired: 'bg-orange-100 text-orange-800'
        };
        
        const categoryIcons = {
            parameter_change: 'fa-sliders-h',
            treasury_allocation: 'fa-coins',
            protocol_upgrade: 'fa-rocket',
            ecosystem_initiative: 'fa-leaf',
            emergency_action: 'fa-exclamation-triangle'
        };
        
        const totalVotes = parseFloat(proposal.votes_for || 0) + 
                          parseFloat(proposal.votes_against || 0) + 
                          parseFloat(proposal.votes_abstain || 0);
        
        const forPercentage = totalVotes > 0 ? (parseFloat(proposal.votes_for || 0) / totalVotes * 100) : 0;
        const againstPercentage = totalVotes > 0 ? (parseFloat(proposal.votes_against || 0) / totalVotes * 100) : 0;
        
        return `
            <div class="proposal-card bg-brand-bg-secondary rounded-lg shadow-md p-6 cursor-pointer" onclick="window.GovernanceModals.openProposalDetail('${proposal.proposal_id}')">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="status-badge px-3 py-1 rounded-full ${statusColors[proposal.status] || 'bg-brand-bg-secondary text-brand-text-primary'}">
                                ${proposal.status}
                            </span>
                            <span class="text-sm text-gray-500">
                                <i class="fas ${categoryIcons[proposal.category] || 'fa-file'} mr-1"></i>
                                ${proposal.category.replace('_', ' ')}
                            </span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2 hover:text-purple-600 transition">
                            ${this.escapeHtml(proposal.title)}
                        </h3>
                        <p class="text-gray-600 line-clamp-2">
                            ${this.escapeHtml(proposal.description || '').substring(0, 200)}...
                        </p>
                    </div>
                    <div class="ml-4 text-right">
                        <div class="text-sm text-gray-500 mb-1">Proposed by</div>
                        <div class="font-mono text-xs text-brand-text-primary">${this.truncateAddress(proposal.proposer_wallet)}</div>
                    </div>
                </div>
                
                <!-- Voting Progress -->
                ${proposal.status === 'active' || proposal.status === 'succeeded' || proposal.status === 'defeated' ? `
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-green-600 font-semibold">
                                <i class="fas fa-thumbs-up mr-1"></i>
                                For: ${proposal.votes_for_formatted || '0'}
                            </span>
                            <span class="text-red-300 font-semibold">
                                <i class="fas fa-thumbs-down mr-1"></i>
                                Against: ${proposal.votes_against_formatted || '0'}
                            </span>
                        </div>
                        <div class="relative w-full h-3 bg-brand-border rounded-full overflow-hidden">
                            <div class="progress-bar absolute left-0 top-0 h-full bg-green-500" style="width: ${forPercentage}%"></div>
                            <div class="progress-bar absolute right-0 top-0 h-full bg-red-500" style="width: ${againstPercentage}%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>${forPercentage.toFixed(1)}%</span>
                            <span>${againstPercentage.toFixed(1)}%</span>
                        </div>
                    </div>
                ` : ''}
                
                <!-- Footer -->
                <div class="flex items-center justify-between pt-4 border-t border-brand-border">
                    <div class="flex items-center gap-4 text-sm text-gray-500">
                        ${proposal.quorum_reached ? 
                            '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Quorum Reached</span>' : 
                            '<span class="text-orange-600"><i class="fas fa-clock mr-1"></i>Quorum Pending</span>'
                        }
                        ${proposal.status === 'active' && proposal.time_remaining ? 
                            `<span><i class="fas fa-hourglass-half mr-1"></i>${proposal.time_remaining}</span>` : 
                            ''
                        }
                    </div>
                    <button onclick="event.stopPropagation(); window.GovernanceModals.openProposalDetail('${proposal.proposal_id}')" class="text-purple-600 hover:text-purple-700 font-semibold">
                        View Details <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * Render pagination
     */
    renderPagination(pagination) {
        if (!this.paginationContainer) return;
        
        if (!pagination.total_pages || pagination.total_pages <= 1) {
            this.paginationContainer.innerHTML = '';
            return;
        }
        
        const currentPage = pagination.current_page || 1;
        const totalPages = pagination.total_pages || 1;
        
        let pages = [];
        
        // Always show first page
        pages.push(1);
        
        // Show pages around current page
        for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
            pages.push(i);
        }
        
        // Always show last page
        if (totalPages > 1) {
            pages.push(totalPages);
        }
        
        // Remove duplicates and sort
        pages = [...new Set(pages)].sort((a, b) => a - b);
        
        this.paginationContainer.innerHTML = `
            <div class="flex items-center gap-2">
                <button 
                    onclick="window.GovernanceProposals.loadProposals(window.GovernanceProposals.currentFilters, ${currentPage - 1})"
                    ${currentPage === 1 ? 'disabled' : ''}
                    class="px-4 py-2 rounded-lg ${currentPage === 1 ? 'bg-brand-bg-primary text-brand-text-secondary cursor-not-allowed' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-brand-bg-primary border border-brand-border'}"
                >
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                ${pages.map((page, index) => {
                    const prevPage = pages[index - 1];
                    const gap = prevPage && page - prevPage > 1 ? '<span class="px-2 text-gray-400">...</span>' : '';
                    
                    return `
                        ${gap}
                        <button 
                            onclick="window.GovernanceProposals.loadProposals(window.GovernanceProposals.currentFilters, ${page})"
                            class="px-4 py-2 rounded-lg ${page === currentPage ? 'bg-purple-600 text-white' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-brand-bg-primary border border-brand-border'}"
                        >
                            ${page}
                        </button>
                    `;
                }).join('')}
                
                <button 
                    onclick="window.GovernanceProposals.loadProposals(window.GovernanceProposals.currentFilters, ${currentPage + 1})"
                    ${currentPage === totalPages ? 'disabled' : ''}
                    class="px-4 py-2 rounded-lg ${currentPage === totalPages ? 'bg-brand-bg-primary text-brand-text-secondary cursor-not-allowed' : 'bg-brand-bg-secondary text-brand-text-primary hover:bg-brand-bg-primary border border-brand-border'}"
                >
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Truncate Ethereum address
     */
    truncateAddress(address) {
        if (!address) return 'N/A';
        return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
    }
}

// Export as global
window.GovernanceProposals = new GovernanceProposals();
