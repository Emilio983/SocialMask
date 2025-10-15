/**
 * ============================================
 * QUADRATIC VOTING UI CONTROLLER
 * ============================================
 * Handles quadratic voting with sqrt(balance) power calculation
 */

class QuadraticVotingUI {
    constructor() {
        this.apiBase = '/api/governance';
        this.contractAddress = null;
        this.userAddress = null;
        this.userBalance = 0;
        this.userVotePower = 0;
    }

    /**
     * Initialize quadratic voting system
     */
    async init(contractAddress) {
        this.contractAddress = contractAddress;
        
        if (typeof window.smartWalletProvider !== 'undefined') {
            try {
                const accounts = await window.smartWalletProvider.request({ 
                    method: 'eth_requestAccounts' 
                });
                this.userAddress = accounts[0];
                
                await this.updateUserVotePower();
                await this.loadActiveProposals();
                
                console.log('✅ Quadratic Voting initialized:', {
                    contract: this.contractAddress,
                    user: this.userAddress,
                    balance: this.userBalance,
                    votePower: this.userVotePower
                });
                
            } catch (error) {
                console.error('❌ Failed to initialize:', error);
            }
        }
    }

    /**
     * Calculate quadratic vote power: sqrt(balance)
     */
    calculateQuadraticPower(balance) {
        if (balance <= 0) return 0;
        return Math.sqrt(parseFloat(balance));
    }

    /**
     * Calculate power reduction from quadratic formula
     */
    calculatePowerReduction(balance) {
        if (balance <= 0) return 0;
        const linear = parseFloat(balance);
        const quadratic = Math.sqrt(linear);
        return ((linear - quadratic) / linear * 100).toFixed(2);
    }

    /**
     * Update user's voting power
     */
    async updateUserVotePower() {
        try {
            const response = await fetch(
                `${this.apiBase}/get-vote-power.php?address=${this.userAddress}`
            );
            const result = await response.json();
            
            if (result.success) {
                this.userBalance = parseFloat(result.balance);
                this.userVotePower = result.votePower;
                
                this.displayVotePower();
            }
        } catch (error) {
            console.error('Failed to get vote power:', error);
        }
    }

    /**
     * Display user's vote power with comparison
     */
    displayVotePower() {
        const container = document.getElementById('user-vote-power');
        if (!container) return;
        
        const reduction = this.calculatePowerReduction(this.userBalance);
        
        container.innerHTML = `
            <div class="vote-power-display">
                <div class="power-comparison">
                    <div class="power-item">
                        <div class="label">Your Token Balance</div>
                        <div class="value">${this.formatNumber(this.userBalance)}</div>
                    </div>
                    
                    <div class="power-arrow">→</div>
                    
                    <div class="power-item quadratic">
                        <div class="label">Your Vote Power (√Balance)</div>
                        <div class="value">${this.formatNumber(this.userVotePower)}</div>
                    </div>
                </div>
                
                <div class="power-info">
                    <i class="fas fa-info-circle"></i>
                    <span>Quadratic voting reduces your voting power by ${reduction}% compared to linear voting. This creates a more democratic governance system!</span>
                </div>
                
                <div class="power-benefits">
                    <h4>🎯 Why Quadratic Voting?</h4>
                    <ul>
                        <li><strong>Reduces Whale Dominance:</strong> Large holders have less overwhelming influence</li>
                        <li><strong>Empowers Small Holders:</strong> Your voice matters more proportionally</li>
                        <li><strong>More Democratic:</strong> Prevents plutocracy in governance</li>
                    </ul>
                </div>
            </div>
        `;
    }

    /**
     * Cast quadratic vote
     */
    async castVote(proposalId, support) {
        try {
            if (!this.userAddress) {
                throw new Error('Please connect your wallet first');
            }

            if (this.userBalance === 0) {
                throw new Error('You need tokens to vote');
            }

            this.showLoading('Submitting your quadratic vote...');

            const response = await fetch(`${this.apiBase}/quadratic-vote.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    proposalId: proposalId,
                    voterAddress: this.userAddress,
                    support: support,
                    tokenBalance: this.userBalance,
                    votePower: this.userVotePower
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to cast vote');
            }

            this.hideLoading();
            this.showSuccess(
                `Vote cast! Your quadratic power: ${this.formatNumber(this.userVotePower)} (${this.calculatePowerReduction(this.userBalance)}% reduction)`
            );
            
            setTimeout(() => this.loadActiveProposals(), 1000);

        } catch (error) {
            this.hideLoading();
            this.showError(error.message);
        }
    }

    /**
     * Load active proposals
     */
    async loadActiveProposals() {
        try {
            const response = await fetch(
                `${this.apiBase}/quadratic-list.php?status=ACTIVE&voter=${this.userAddress}`
            );
            const result = await response.json();

            if (result.success) {
                this.renderProposals(result.proposals);
            }
        } catch (error) {
            console.error('Failed to load proposals:', error);
        }
    }

    /**
     * Render proposals with quadratic results
     */
    renderProposals(proposals) {
        const container = document.getElementById('quadratic-proposals');
        if (!container) return;

        if (proposals.length === 0) {
            container.innerHTML = `
                <div class="no-proposals">
                    <i class="fas fa-vote-yea"></i>
                    <p>No active proposals</p>
                </div>
            `;
            return;
        }

        container.innerHTML = proposals.map(proposal => {
            const totalVotes = parseFloat(proposal.votes_for) + parseFloat(proposal.votes_against);
            const approvalPercent = totalVotes > 0 
                ? (parseFloat(proposal.votes_for) / totalVotes * 100).toFixed(1)
                : 0;

            return `
                <div class="quadratic-proposal" data-id="${proposal.proposal_id}">
                    <div class="proposal-header">
                        <h3>${proposal.title}</h3>
                        <span class="proposal-id">#${proposal.proposal_id}</span>
                    </div>
                    
                    <p class="description">${proposal.description}</p>
                    
                    <div class="voting-results">
                        <div class="result-bars">
                            <div class="result-bar for" style="width: ${approvalPercent}%">
                                <span class="bar-label">For: ${this.formatNumber(proposal.votes_for)}</span>
                            </div>
                            <div class="result-bar against" style="width: ${100 - approvalPercent}%">
                                <span class="bar-label">Against: ${this.formatNumber(proposal.votes_against)}</span>
                            </div>
                        </div>
                        
                        <div class="result-stats">
                            <div class="stat">
                                <i class="fas fa-users"></i>
                                <span>${proposal.total_voters} voters</span>
                            </div>
                            <div class="stat">
                                <i class="fas fa-chart-line"></i>
                                <span>${approvalPercent}% approval</span>
                            </div>
                            <div class="stat">
                                <i class="fas fa-clock"></i>
                                <span>${this.formatTimeRemaining(proposal.voting_ends)}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${!proposal.has_voted ? `
                        <div class="vote-actions">
                            <button onclick="quadraticVoting.castVote(${proposal.proposal_id}, 1)" 
                                    class="btn-vote for">
                                <i class="fas fa-check"></i> Vote For
                            </button>
                            <button onclick="quadraticVoting.castVote(${proposal.proposal_id}, 0)" 
                                    class="btn-vote against">
                                <i class="fas fa-times"></i> Vote Against
                            </button>
                            <button onclick="quadraticVoting.castVote(${proposal.proposal_id}, 2)" 
                                    class="btn-vote abstain">
                                <i class="fas fa-minus"></i> Abstain
                            </button>
                        </div>
                        <div class="vote-preview">
                            <i class="fas fa-calculator"></i>
                            Your vote power: <strong>${this.formatNumber(this.userVotePower)}</strong>
                            (${this.calculatePowerReduction(this.userBalance)}% reduction)
                        </div>
                    ` : `
                        <div class="voted-badge">
                            <i class="fas fa-check-circle"></i>
                            You voted on this proposal
                        </div>
                    `}
                    
                    <button onclick="quadraticVoting.showComparison(${proposal.proposal_id})" 
                            class="btn-details">
                        <i class="fas fa-chart-pie"></i> View Quadratic vs Linear
                    </button>
                </div>
            `;
        }).join('');
    }

    /**
     * Show quadratic vs linear comparison
     */
    async showComparison(proposalId) {
        try {
            const response = await fetch(
                `${this.apiBase}/quadratic-comparison.php?proposalId=${proposalId}`
            );
            const result = await response.json();

            if (!result.success) {
                throw new Error('Failed to load comparison data');
            }

            this.renderComparisonModal(result.data);

        } catch (error) {
            this.showError(error.message);
        }
    }

    /**
     * Render comparison modal
     */
    renderComparisonModal(data) {
        const modal = document.getElementById('comparison-modal') || this.createComparisonModal();
        
        modal.innerHTML = `
            <div class="modal-content-comparison">
                <span class="close" onclick="document.getElementById('comparison-modal').style.display='none'">&times;</span>
                
                <h2>📊 Quadratic vs Linear Voting Impact</h2>
                
                <div class="comparison-stats">
                    <div class="stat-box">
                        <div class="stat-label">Linear Voting (Traditional)</div>
                        <div class="stat-value">${this.formatNumber(data.linearTotal)}</div>
                        <div class="stat-note">Whales dominate</div>
                    </div>
                    
                    <div class="stat-arrow">→</div>
                    
                    <div class="stat-box highlight">
                        <div class="stat-label">Quadratic Voting (Democratic)</div>
                        <div class="stat-value">${this.formatNumber(data.quadraticTotal)}</div>
                        <div class="stat-note">More balanced</div>
                    </div>
                </div>
                
                <div class="holder-breakdown">
                    <h3>Power Distribution by Holder Type</h3>
                    <table class="comparison-table">
                        <thead>
                            <tr>
                                <th>Holder Type</th>
                                <th>Count</th>
                                <th>Linear Power</th>
                                <th>Quadratic Power</th>
                                <th>Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.breakdown.map(row => `
                                <tr>
                                    <td>${row.type}</td>
                                    <td>${row.count}</td>
                                    <td>${this.formatNumber(row.linear)}</td>
                                    <td>${this.formatNumber(row.quadratic)}</td>
                                    <td class="${row.change < 0 ? 'negative' : 'positive'}">
                                        ${row.change > 0 ? '+' : ''}${row.change}%
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        modal.style.display = 'flex';
    }

    /**
     * Create comparison modal if doesn't exist
     */
    createComparisonModal() {
        const modal = document.createElement('div');
        modal.id = 'comparison-modal';
        modal.className = 'modal';
        document.body.appendChild(modal);
        return modal;
    }

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return parseFloat(num).toLocaleString('en-US', {
            maximumFractionDigits: 2,
            minimumFractionDigits: 0
        });
    }

    /**
     * Format time remaining
     */
    formatTimeRemaining(endsAt) {
        const now = new Date();
        const ends = new Date(endsAt);
        const diff = ends - now;
        
        if (diff < 0) return 'Ended';
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        
        if (days > 0) return `${days}d ${hours}h left`;
        return `${hours}h left`;
    }

    /**
     * Show loading
     */
    showLoading(message) {
        const loader = document.getElementById('quadratic-loader') || this.createLoader();
        loader.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${message}`;
        loader.style.display = 'flex';
    }

    /**
     * Hide loading
     */
    hideLoading() {
        const loader = document.getElementById('quadratic-loader');
        if (loader) loader.style.display = 'none';
    }

    /**
     * Create loader
     */
    createLoader() {
        const loader = document.createElement('div');
        loader.id = 'quadratic-loader';
        loader.className = 'loader-overlay';
        document.body.appendChild(loader);
        return loader;
    }

    /**
     * Show success notification
     */
    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    /**
     * Show error notification
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
const quadraticVoting = new QuadraticVotingUI();

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
    const contractAddress = document.querySelector('[data-quadratic-contract]')?.dataset.quadraticContract;
    if (contractAddress) {
        quadraticVoting.init(contractAddress);
    }
});
