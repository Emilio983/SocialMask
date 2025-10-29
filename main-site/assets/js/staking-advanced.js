/**
 * Staking Advanced Features Manager
 * Handles auto-compound, referrals, multi-pool, and governance
 */

class StakingAdvancedManager {
    constructor(stakingManager) {
        this.stakingManager = stakingManager;
        this.contractAdvanced = null;
        this.autoCompoundInterval = null;
    }

    /**
     * Initialize advanced contract
     */
    async initialize(advancedContractAddress, advancedContractABI) {
        if (!this.stakingManager || !this.stakingManager.web3) {
            throw new Error('Staking manager not initialized');
        }

        this.contractAdvanced = new this.stakingManager.web3.eth.Contract(
            advancedContractABI,
            advancedContractAddress
        );

        console.log('Advanced features initialized');
    }

    // ============================================
    // AUTO-COMPOUND
    // ============================================

    /**
     * Enable auto-compound
     */
    async enableAutoCompound(frequency, minRewards) {
        try {
            const accounts = await this.stakingManager.web3.eth.getAccounts();
            const account = accounts[0];

            // Convert frequency to seconds (e.g., 1 day = 86400)
            const frequencySeconds = frequency * 86400;
            const minRewardsWei = this.stakingManager.web3.utils.toWei(minRewards.toString(), 'ether');

            // Call smart contract
            const tx = await this.contractAdvanced.methods
                .enableAutoCompound(frequencySeconds, minRewardsWei)
                .send({ from: account });

            // Register in backend
            await this.registerAutoCompoundInBackend(
                'enable',
                frequencySeconds,
                minRewards,
                tx.transactionHash
            );

            return tx;
        } catch (error) {
            console.error('Error enabling auto-compound:', error);
            throw error;
        }
    }

    /**
     * Disable auto-compound
     */
    async disableAutoCompound() {
        try {
            const accounts = await this.stakingManager.web3.eth.getAccounts();
            const account = accounts[0];

            const tx = await this.contractAdvanced.methods
                .disableAutoCompound()
                .send({ from: account });

            // Register in backend
            await this.registerAutoCompoundInBackend('disable', 0, 0, tx.transactionHash);

            return tx;
        } catch (error) {
            console.error('Error disabling auto-compound:', error);
            throw error;
        }
    }

    /**
     * Execute auto-compound for a user
     */
    async executeAutoCompound(userAddress) {
        try {
            const accounts = await this.stakingManager.web3.eth.getAccounts();
            const account = accounts[0];

            const tx = await this.contractAdvanced.methods
                .executeAutoCompound(userAddress)
                .send({ from: account });

            return tx;
        } catch (error) {
            console.error('Error executing auto-compound:', error);
            throw error;
        }
    }

    /**
     * Get auto-compound settings
     */
    async getAutoCompoundSettings(userAddress) {
        try {
            const settings = await this.contractAdvanced.methods
                .autoCompoundSettings(userAddress)
                .call();

            return {
                enabled: settings.enabled,
                frequency: parseInt(settings.frequency),
                lastCompound: parseInt(settings.lastCompound),
                minRewardsToCompound: this.stakingManager.web3.utils.fromWei(
                    settings.minRewardsToCompound,
                    'ether'
                ),
                nextCompoundIn: this.calculateNextCompound(
                    parseInt(settings.lastCompound),
                    parseInt(settings.frequency)
                )
            };
        } catch (error) {
            console.error('Error getting auto-compound settings:', error);
            return null;
        }
    }

    /**
     * Calculate time until next compound
     */
    calculateNextCompound(lastCompound, frequency) {
        const now = Math.floor(Date.now() / 1000);
        const elapsed = now - lastCompound;
        const remaining = Math.max(0, frequency - elapsed);
        return remaining;
    }

    /**
     * Register auto-compound in backend
     */
    async registerAutoCompoundInBackend(action, frequency, minRewards, txHash) {
        const userId = sessionStorage.getItem('user_id') || 1;

        const response = await fetch('/api/staking/auto_compound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: parseInt(userId),
                action: action,
                frequency: frequency,
                min_rewards: minRewards,
                tx_hash: txHash
            })
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message);
        return data;
    }

    // ============================================
    // REFERRAL SYSTEM
    // ============================================

    /**
     * Register referral
     */
    async registerReferral(referrerAddress) {
        try {
            const accounts = await this.stakingManager.web3.eth.getAccounts();
            const account = accounts[0];

            const tx = await this.contractAdvanced.methods
                .registerReferral(referrerAddress)
                .send({ from: account });

            return tx;
        } catch (error) {
            console.error('Error registering referral:', error);
            throw error;
        }
    }

    /**
     * Get referral stats
     */
    async getReferralStats(userAddress) {
        try {
            const stats = await this.contractAdvanced.methods
                .getReferralStats(userAddress)
                .call();

            return {
                referrer: stats.referrer,
                totalReferred: parseInt(stats.totalReferred),
                referralRewards: this.stakingManager.web3.utils.fromWei(stats.referralRewards, 'ether'),
                bonusAPY: parseInt(stats.bonusAPY) / 100, // Convert basis points to percentage
                referredUsers: stats.referredUsers
            };
        } catch (error) {
            console.error('Error getting referral stats:', error);
            return null;
        }
    }

    /**
     * Claim referral rewards
     */
    async claimReferralRewards() {
        try {
            const accounts = await this.stakingManager.web3.eth.getAccounts();
            const account = accounts[0];

            const tx = await this.contractAdvanced.methods
                .claimReferralRewards()
                .send({ from: account });

            // Register in backend
            await this.registerReferralClaimInBackend(tx.transactionHash);

            return tx;
        } catch (error) {
            console.error('Error claiming referral rewards:', error);
            throw error;
        }
    }

    /**
     * Get referral code from backend
     */
    async getReferralCode() {
        const userId = sessionStorage.getItem('user_id') || 1;

        const response = await fetch(`/api/staking/referral.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: parseInt(userId),
                action: 'get_stats'
            })
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message);
        return data.data.referral_code;
    }

    /**
     * Register with referral code
     */
    async registerWithReferralCode(referralCode) {
        const userId = sessionStorage.getItem('user_id') || 1;

        const response = await fetch('/api/staking/referral.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: parseInt(userId),
                action: 'register',
                referral_code: referralCode
            })
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message);
        return data;
    }

    /**
     * Register referral claim in backend
     */
    async registerReferralClaimInBackend(txHash) {
        const userId = sessionStorage.getItem('user_id') || 1;

        const response = await fetch('/api/staking/referral.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: parseInt(userId),
                action: 'claim',
                tx_hash: txHash
            })
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message);
        return data;
    }

    // ============================================
    // MULTI-POOL STAKING
    // ============================================

    /**
     * Stake in additional pool
     */
    async stakeInAdditionalPool(amount, poolId) {
        try {
            const accounts = await this.stakingManager.web3.eth.getAccounts();
            const account = accounts[0];
            const amountWei = this.stakingManager.web3.utils.toWei(amount.toString(), 'ether');

            const tx = await this.contractAdvanced.methods
                .stakeInAdditionalPool(amountWei, poolId)
                .send({ from: account });

            return tx;
        } catch (error) {
            console.error('Error staking in additional pool:', error);
            throw error;
        }
    }

    /**
     * Get user active pools
     */
    async getUserActivePools(userAddress) {
        try {
            const poolIds = await this.contractAdvanced.methods
                .getUserActivePools(userAddress)
                .call();

            return poolIds.map(id => parseInt(id));
        } catch (error) {
            console.error('Error getting active pools:', error);
            return [];
        }
    }

    /**
     * Get total staked across all pools
     */
    async getTotalStakedAllPools(userAddress) {
        try {
            const total = await this.contractAdvanced.methods
                .getTotalStakedAllPools(userAddress)
                .call();

            return this.stakingManager.web3.utils.fromWei(total, 'ether');
        } catch (error) {
            console.error('Error getting total staked:', error);
            return '0';
        }
    }

    // ============================================
    // GOVERNANCE TOKENS
    // ============================================

    /**
     * Get governance token balance
     */
    async getGovernanceBalance(userAddress) {
        try {
            const balance = await this.contractAdvanced.methods
                .governanceBalance(userAddress)
                .call();

            return this.stakingManager.web3.utils.fromWei(balance, 'ether');
        } catch (error) {
            console.error('Error getting governance balance:', error);
            return '0';
        }
    }

    /**
     * Claim governance tokens
     */
    async claimGovernanceTokens() {
        try {
            const accounts = await this.stakingManager.web3.eth.getAccounts();
            const account = accounts[0];

            const tx = await this.contractAdvanced.methods
                .claimGovernanceTokens()
                .send({ from: account });

            return tx;
        } catch (error) {
            console.error('Error claiming governance tokens:', error);
            throw error;
        }
    }

    // ============================================
    // ANALYTICS
    // ============================================

    /**
     * Get detailed user stats (from smart contract)
     */
    async getUserDetailedStats(userAddress) {
        try {
            const stats = await this.contractAdvanced.methods
                .getUserDetailedStats(userAddress)
                .call();

            return {
                totalStaked: this.stakingManager.web3.utils.fromWei(stats.totalStaked, 'ether'),
                pendingRewards: this.stakingManager.web3.utils.fromWei(stats.pendingRewards, 'ether'),
                totalRewardsClaimed: this.stakingManager.web3.utils.fromWei(stats.totalRewardsClaimed, 'ether'),
                referralCount: parseInt(stats.referralCount),
                referralRewards: this.stakingManager.web3.utils.fromWei(stats.referralRewards, 'ether'),
                autoCompoundEnabled: stats.autoCompoundEnabled,
                governanceTokens: this.stakingManager.web3.utils.fromWei(stats.governanceTokens, 'ether'),
                activePools: stats.activePools.map(id => parseInt(id))
            };
        } catch (error) {
            console.error('Error getting detailed stats:', error);
            return null;
        }
    }

    /**
     * Get analytics from backend
     */
    async getAnalytics(type = 'user') {
        const userId = sessionStorage.getItem('user_id') || 1;
        const params = new URLSearchParams({
            type: type
        });

        if (type === 'user') {
            params.append('user_id', userId);
        }

        const response = await fetch(`/api/staking/analytics.php?${params}`);
        const data = await response.json();

        if (!data.success) throw new Error(data.message);
        return data.data;
    }

    // ============================================
    // UTILITIES
    // ============================================

    /**
     * Format time remaining
     */
    formatTimeRemaining(seconds) {
        if (seconds <= 0) return 'Ready now';

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);

        if (days > 0) return `${days}d ${hours}h`;
        if (hours > 0) return `${hours}h ${minutes}m`;
        return `${minutes}m`;
    }

    /**
     * Generate referral link
     */
    generateReferralLink(referralCode) {
        const baseUrl = window.location.origin;
        return `${baseUrl}/pages/staking/dashboard.php?ref=${referralCode}`;
    }

    /**
     * Copy to clipboard
     */
    copyToClipboard(text) {
        return navigator.clipboard.writeText(text);
    }

    /**
     * Cleanup
     */
    cleanup() {
        if (this.autoCompoundInterval) {
            clearInterval(this.autoCompoundInterval);
        }
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StakingAdvancedManager;
}
