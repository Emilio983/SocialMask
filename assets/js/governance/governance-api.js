/**
 * ============================================
 * GOVERNANCE API MODULE
 * ============================================
 * Handles all API calls to governance backend
 */

class GovernanceAPI {
    constructor() {
        this.baseUrl = window.__SPHERA_GOVERNANCE__?.apiBase || '/api/governance';
    }
    
    /**
     * GET: System statistics
     */
    async getStats() {
        try {
            const response = await fetch(`${this.baseUrl}/get_stats.php`);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to fetch stats');
            }
            
            return data.stats || data;
        } catch (error) {
            console.error('Error fetching stats:', error);
            throw error;
        }
    }
    
    /**
     * GET: Voting power for a wallet
     */
    async getVotingPower(wallet) {
        try {
            const response = await fetch(`${this.baseUrl}/get_voting_power.php`);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to fetch voting power');
            }
            
            return data;
        } catch (error) {
            console.error('Error fetching voting power:', error);
            throw error;
        }
    }
    
    /**
     * GET: List of proposals with filters
     */
    async getProposals(filters = {}) {
        try {
            const params = new URLSearchParams();
            
            if (filters.category) params.append('category', filters.category);
            if (filters.status) params.append('status', filters.status);
            if (filters.search) params.append('search', filters.search);
            if (filters.page) params.append('page', filters.page);
            if (filters.per_page) params.append('per_page', filters.per_page);
            
            const url = `${this.baseUrl}/get_proposals.php${params.toString() ? '?' + params.toString() : ''}`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to fetch proposals');
            }
            
            return data;
        } catch (error) {
            console.error('Error fetching proposals:', error);
            throw error;
        }
    }
    
    /**
     * GET: Single proposal details
     */
    async getProposal(proposalId) {
        try {
            const response = await fetch(`${this.baseUrl}/get_proposal_detail.php?id=${encodeURIComponent(proposalId)}`);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to fetch proposal');
            }
            
            return data;
        } catch (error) {
            console.error('Error fetching proposal:', error);
            throw error;
        }
    }
    
    /**
     * POST: Cast vote on proposal
     */
    async castVote(proposalId, voteType, signature = null) {
        try {
            const config = window.__SPHERA_GOVERNANCE__;
            
            const response = await fetch(`${this.baseUrl}/cast_vote.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    proposal_id: proposalId,
                    support: voteType,
                    reason: '',
                    signature: signature
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Failed to cast vote');
            }
            
            return data;
        } catch (error) {
            console.error('Error casting vote:', error);
            throw error;
        }
    }
    
    /**
     * POST: Delegate voting power
     */
    async delegate(delegatee, signature = null) {
        try {
            const response = await fetch(`${this.baseUrl}/delegate_votes.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    delegate_address: delegatee,
                    signature: signature
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Failed to delegate');
            }
            
            return data;
        } catch (error) {
            console.error('Error delegating:', error);
            throw error;
        }
    }
    
    /**
     * POST: Create new proposal
     */
    async createProposal(proposalData) {
        try {
            const response = await fetch(`${this.baseUrl}/create_proposal.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(proposalData)
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Failed to create proposal');
            }
            
            return data;
        } catch (error) {
            console.error('Error creating proposal:', error);
            throw error;
        }
    }
}

// Export as global
window.GovernanceAPI = new GovernanceAPI();
