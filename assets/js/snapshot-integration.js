/**
 * ============================================
 * SNAPSHOT INTEGRATION UI
 * ============================================
 * Display and interact with Snapshot proposals
 */

class SnapshotIntegration {
    constructor() {
        this.apiBase = '/api/governance';
        this.snapshotSpace = 'sphera.eth';
        this.proposals = [];
        this.autoSyncInterval = null;
    }

    /**
     * Initialize Snapshot integration
     */
    async init(space = null) {
        if (space) this.snapshotSpace = space;
        
        await this.loadProposals();
        this.startAutoSync();
        this.setupEventListeners();
    }

    /**
     * Load proposals from database
     */
    async loadProposals() {
        try {
            const response = await fetch(`${this.apiBase}/snapshot-proposals.php`);
            const result = await response.json();

            if (result.success) {
                this.proposals = result.proposals;
                this.renderProposals(result.proposals);
            }
        } catch (error) {
            console.error('Failed to load proposals:', error);
        }
    }

    /**
     * Sync with Snapshot.org
     */
    async syncWithSnapshot() {
        const syncBtn = document.getElementById('sync-snapshot-btn');
        if (syncBtn) {
            syncBtn.disabled = true;
            syncBtn.innerHTML = '<i class="fas fa-sync fa-spin"></i> Syncing...';
        }

        try {
            const response = await fetch(`${this.apiBase}/snapshot-sync.php`, {
                method: 'POST'
            });
            const result = await response.json();

            if (result.success) {
                this.showNotification(
                    `Synced ${result.proposals_synced} proposals and ${result.votes_synced} votes`,
                    'success'
                );
                await this.loadProposals();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            this.showNotification(`Sync failed: ${error.message}`, 'error');
        } finally {
            if (syncBtn) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<i class="fas fa-sync"></i> Sync Now';
            }
        }
    }

    /**
     * Render proposals list
     */
    renderProposals(proposals) {
        const container = document.getElementById('snapshot-proposals-list');
        if (!container) return;

        if (proposals.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox fa-3x"></i>
                    <h3>No proposals yet</h3>
                    <p>Proposals from Snapshot will appear here</p>
                    <button onclick="snapshotIntegration.syncWithSnapshot()" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Sync with Snapshot
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = proposals.map(proposal => this.renderProposalCard(proposal)).join('');
    }

    /**
     * Render individual proposal card
     */
    renderProposalCard(proposal) {
        const start = new Date(proposal.start_timestamp * 1000);
        const end = new Date(proposal.end_timestamp * 1000);
        const now = Date.now();
        
        let statusBadge = '';
        let statusClass = '';
        
        if (now < proposal.start_timestamp * 1000) {
            statusBadge = '<span class="status-badge pending">Pending</span>';
            statusClass = 'proposal-pending';
        } else if (now >= proposal.start_timestamp * 1000 && now <= proposal.end_timestamp * 1000) {
            statusBadge = '<span class="status-badge active">Active</span>';
            statusClass = 'proposal-active';
        } else {
            statusBadge = '<span class="status-badge closed">Closed</span>';
            statusClass = 'proposal-closed';
        }

        const choices = JSON.parse(proposal.choices || '[]');
        const scores = JSON.parse(proposal.scores || '[]');

        return `
            <div class="snapshot-proposal-card ${statusClass}" data-proposal-id="${proposal.snapshot_id}">
                <div class="proposal-header">
                    <div class="proposal-title-row">
                        <h3>${this.escapeHtml(proposal.title)}</h3>
                        ${statusBadge}
                    </div>
                    <div class="proposal-meta">
                        <span><i class="fas fa-user"></i> ${this.shortAddress(proposal.proposer_address)}</span>
                        <span><i class="fas fa-vote-yea"></i> ${proposal.votes_count} votes</span>
                        <span><i class="fas fa-chart-bar"></i> ${proposal.scores_total.toFixed(2)} voting power</span>
                    </div>
                </div>

                <div class="proposal-body">
                    ${this.formatMarkdown(proposal.body)}
                </div>

                <div class="proposal-dates">
                    <div class="date-item">
                        <i class="fas fa-calendar-alt"></i>
                        <div>
                            <strong>Start:</strong>
                            <span>${start.toLocaleDateString()} ${start.toLocaleTimeString()}</span>
                        </div>
                    </div>
                    <div class="date-item">
                        <i class="fas fa-calendar-check"></i>
                        <div>
                            <strong>End:</strong>
                            <span>${end.toLocaleDateString()} ${end.toLocaleTimeString()}</span>
                        </div>
                    </div>
                </div>

                <div class="proposal-results">
                    <h4>Results</h4>
                    ${choices.map((choice, index) => {
                        const score = scores[index] || 0;
                        const percentage = proposal.scores_total > 0 
                            ? (score / proposal.scores_total * 100).toFixed(2)
                            : 0;
                        
                        return `
                            <div class="result-item">
                                <div class="result-label">
                                    <span>${this.escapeHtml(choice)}</span>
                                    <span class="result-score">${score.toFixed(2)} (${percentage}%)</span>
                                </div>
                                <div class="result-bar">
                                    <div class="result-fill" style="width: ${percentage}%"></div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>

                <div class="proposal-actions">
                    <a href="https://snapshot.org/#/${this.snapshotSpace}/proposal/${proposal.snapshot_id}" 
                       target="_blank" 
                       class="btn btn-secondary">
                        <i class="fas fa-external-link-alt"></i> View on Snapshot
                    </a>
                    ${proposal.state === 'CLOSED' && !proposal.execution_hash ? `
                        <button onclick="snapshotIntegration.executeProposal('${proposal.snapshot_id}')" 
                                class="btn btn-primary">
                            <i class="fas fa-play"></i> Execute On-Chain
                        </button>
                    ` : ''}
                    ${proposal.execution_hash ? `
                        <span class="executed-badge">
                            <i class="fas fa-check-circle"></i> Executed
                        </span>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Execute proposal on-chain
     */
    async executeProposal(snapshotId) {
        if (!confirm('Execute this proposal on-chain? This will use gas.')) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}/execute-snapshot.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ snapshotId })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Proposal executed successfully!', 'success');
                await this.loadProposals();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            this.showNotification(`Execution failed: ${error.message}`, 'error');
        }
    }

    /**
     * Auto-sync every 5 minutes
     */
    startAutoSync() {
        // Sync every 5 minutes
        this.autoSyncInterval = setInterval(() => {
            this.syncWithSnapshot();
        }, 5 * 60 * 1000);
    }

    /**
     * Stop auto-sync
     */
    stopAutoSync() {
        if (this.autoSyncInterval) {
            clearInterval(this.autoSyncInterval);
            this.autoSyncInterval = null;
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Sync button
        const syncBtn = document.getElementById('sync-snapshot-btn');
        if (syncBtn) {
            syncBtn.addEventListener('click', () => this.syncWithSnapshot());
        }

        // Filter buttons
        const filterBtns = document.querySelectorAll('.filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filter = e.target.dataset.filter;
                this.filterProposals(filter);
            });
        });
    }

    /**
     * Filter proposals
     */
    filterProposals(filter) {
        let filtered = this.proposals;

        switch (filter) {
            case 'active':
                filtered = this.proposals.filter(p => {
                    const now = Date.now();
                    return now >= p.start_timestamp * 1000 && now <= p.end_timestamp * 1000;
                });
                break;
            case 'closed':
                filtered = this.proposals.filter(p => Date.now() > p.end_timestamp * 1000);
                break;
            case 'executed':
                filtered = this.proposals.filter(p => p.execution_hash);
                break;
        }

        this.renderProposals(filtered);
    }

    /**
     * Format markdown (basic)
     */
    formatMarkdown(text) {
        if (!text) return '';
        
        // Truncate long text
        const maxLength = 300;
        if (text.length > maxLength) {
            text = text.substring(0, maxLength) + '...';
        }

        // Basic markdown formatting
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
    }

    /**
     * Utilities
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    shortAddress(address) {
        if (!address) return '';
        return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
    }

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
const snapshotIntegration = new SnapshotIntegration();

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
    const space = document.querySelector('[data-snapshot-space]')?.dataset.snapshotSpace;
    if (space) {
        snapshotIntegration.init(space);
    }
});
