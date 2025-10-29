// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "./TokenStaking.sol";

/**
 * @title TokenStakingAdvanced
 * @dev Advanced staking features: auto-compound, referrals, multi-pool
 */
contract TokenStakingAdvanced is TokenStaking {
    
    // ============================================
    // STATE VARIABLES
    // ============================================
    
    struct ReferralData {
        address referrer;
        uint256 totalReferred;
        uint256 referralRewards;
        uint256 bonusAPY; // Bonus APY in basis points (100 = 1%)
    }
    
    struct AutoCompoundSettings {
        bool enabled;
        uint256 frequency; // Seconds between compounds
        uint256 lastCompound;
        uint256 minRewardsToCompound;
    }
    
    // Referral system
    mapping(address => ReferralData) public referralData;
    mapping(address => address[]) public referrals; // referrer => referred users
    uint256 public referralBonusAPY = 500; // 5% bonus APY for referrer
    uint256 public referredBonusAPY = 200; // 2% bonus APY for referred user
    
    // Auto-compound
    mapping(address => AutoCompoundSettings) public autoCompoundSettings;
    uint256 public autoCompoundFee = 50; // 0.5% fee for gas costs
    
    // Multi-pool staking
    mapping(address => uint256[]) public userActivePools;
    mapping(address => mapping(uint256 => bool)) public isStakedInPool;
    
    // Governance tokens
    IERC20 public governanceToken;
    mapping(address => uint256) public governanceBalance;
    
    // ============================================
    // EVENTS
    // ============================================
    
    event ReferralRegistered(address indexed user, address indexed referrer);
    event ReferralRewardClaimed(address indexed referrer, uint256 amount);
    event AutoCompoundEnabled(address indexed user, uint256 frequency);
    event AutoCompoundExecuted(address indexed user, uint256 amount, uint256 fee);
    event MultiPoolStakeAdded(address indexed user, uint256 indexed poolId, uint256 amount);
    event GovernanceTokensEarned(address indexed user, uint256 amount);
    
    // ============================================
    // CONSTRUCTOR
    // ============================================
    
    constructor(
        IERC20 _stakingToken,
        IERC20 _governanceToken,
        uint256 _rewardRatePerSecond
    ) TokenStaking(_stakingToken, _rewardRatePerSecond) {
        governanceToken = _governanceToken;
    }
    
    // ============================================
    // REFERRAL SYSTEM
    // ============================================
    
    /**
     * @notice Register a referral code
     * @param referrer Address of the referrer
     */
    function registerReferral(address referrer) external {
        require(referrer != address(0), "Invalid referrer");
        require(referrer != msg.sender, "Cannot refer yourself");
        require(referralData[msg.sender].referrer == address(0), "Already has referrer");
        require(stakes[msg.sender].amount == 0, "Already staked, cannot add referrer");
        
        referralData[msg.sender].referrer = referrer;
        referralData[msg.sender].bonusAPY = referredBonusAPY;
        
        referralData[referrer].totalReferred++;
        referralData[referrer].bonusAPY = referralBonusAPY;
        referrals[referrer].push(msg.sender);
        
        emit ReferralRegistered(msg.sender, referrer);
    }
    
    /**
     * @notice Calculate rewards with referral bonus
     */
    function calculateRewardsWithBonus(address user) public view returns (uint256) {
        uint256 baseRewards = calculateRewards(user);
        uint256 bonusAPY = referralData[user].bonusAPY;
        
        if (bonusAPY == 0) return baseRewards;
        
        // Add bonus APY to rewards
        uint256 bonusRewards = (baseRewards * bonusAPY) / 10000;
        return baseRewards + bonusRewards;
    }
    
    /**
     * @notice Claim referral rewards (for referrer)
     */
    function claimReferralRewards() external nonReentrant whenNotPaused {
        uint256 rewards = referralData[msg.sender].referralRewards;
        require(rewards > 0, "No referral rewards");
        
        referralData[msg.sender].referralRewards = 0;
        
        require(stakingToken.transfer(msg.sender, rewards), "Transfer failed");
        
        emit ReferralRewardClaimed(msg.sender, rewards);
    }
    
    /**
     * @notice Get referral statistics
     */
    function getReferralStats(address user) external view returns (
        address referrer,
        uint256 totalReferred,
        uint256 referralRewards,
        uint256 bonusAPY,
        address[] memory referredUsers
    ) {
        ReferralData memory data = referralData[user];
        return (
            data.referrer,
            data.totalReferred,
            data.referralRewards,
            data.bonusAPY,
            referrals[user]
        );
    }
    
    // ============================================
    // AUTO-COMPOUND
    // ============================================
    
    /**
     * @notice Enable auto-compound for rewards
     * @param frequency Seconds between auto-compounds (min 1 day)
     * @param minRewards Minimum rewards to trigger compound
     */
    function enableAutoCompound(uint256 frequency, uint256 minRewards) external {
        require(frequency >= 1 days, "Frequency too low");
        require(stakes[msg.sender].amount > 0, "No active stake");
        
        autoCompoundSettings[msg.sender] = AutoCompoundSettings({
            enabled: true,
            frequency: frequency,
            lastCompound: block.timestamp,
            minRewardsToCompound: minRewards
        });
        
        emit AutoCompoundEnabled(msg.sender, frequency);
    }
    
    /**
     * @notice Disable auto-compound
     */
    function disableAutoCompound() external {
        autoCompoundSettings[msg.sender].enabled = false;
    }
    
    /**
     * @notice Execute auto-compound (can be called by anyone, user pays gas)
     * @param user Address to compound for
     */
    function executeAutoCompound(address user) external nonReentrant whenNotPaused {
        AutoCompoundSettings memory settings = autoCompoundSettings[user];
        
        require(settings.enabled, "Auto-compound not enabled");
        require(
            block.timestamp >= settings.lastCompound + settings.frequency,
            "Too soon to compound"
        );
        
        uint256 rewards = calculateRewardsWithBonus(user);
        require(rewards >= settings.minRewardsToCompound, "Rewards below minimum");
        
        // Verify reward pool has enough
        require(rewardPool >= rewards, "Insufficient reward pool");
        
        // Calculate fee
        uint256 fee = (rewards * autoCompoundFee) / 10000;
        uint256 compoundAmount = rewards - fee;
        
        // Deduct from reward pool
        rewardPool -= rewards;
        
        // Update stake
        stakes[user].amount += compoundAmount;
        stakes[user].rewardDebt = stakes[user].amount * pools[stakes[user].poolId].accRewardPerShare / 1e12;
        
        // Update pool
        pools[stakes[user].poolId].totalStaked += compoundAmount;
        
        // Pay fee to executor
        require(stakingToken.transfer(msg.sender, fee), "Fee transfer failed");
        
        // Update last compound time
        autoCompoundSettings[user].lastCompound = block.timestamp;
        
        // Earn governance tokens
        _earnGovernanceTokens(user, compoundAmount);
        
        emit AutoCompoundExecuted(user, compoundAmount, fee);
    }
    
    // ============================================
    // MULTI-POOL STAKING
    // ============================================
    
    /**
     * @notice Stake in additional pool (multi-pool staking)
     * @param amount Amount to stake
     * @param poolId Pool to stake in
     */
    function stakeInAdditionalPool(uint256 amount, uint256 poolId) external nonReentrant whenNotPaused {
        require(amount > 0, "Cannot stake 0");
        require(poolId < pools.length, "Invalid pool");
        require(amount >= pools[poolId].minStake, "Below minimum stake");
        
        // Update pool rewards
        _updatePool(poolId);
        
        // Transfer tokens
        require(stakingToken.transferFrom(msg.sender, address(this), amount), "Transfer failed");
        
        // Create or update position in this pool
        if (!isStakedInPool[msg.sender][poolId]) {
            userActivePools[msg.sender].push(poolId);
            isStakedInPool[msg.sender][poolId] = true;
        }
        
        // Note: This is simplified. In production, you'd need separate stake tracking per pool
        stakes[msg.sender].amount += amount;
        stakes[msg.sender].startTime = block.timestamp;
        
        // Update pool
        pools[poolId].totalStaked += amount;
        
        emit MultiPoolStakeAdded(msg.sender, poolId, amount);
    }
    
    /**
     * @notice Get all active pools for user
     */
    function getUserActivePools(address user) external view returns (uint256[] memory) {
        return userActivePools[user];
    }
    
    /**
     * @notice Get total staked across all pools
     */
    function getTotalStakedAllPools(address user) external view returns (uint256) {
        return stakes[user].amount;
    }
    
    // ============================================
    // GOVERNANCE TOKENS
    // ============================================
    
    /**
     * @notice Earn governance tokens based on staking activity
     */
    function _earnGovernanceTokens(address user, uint256 amount) internal {
        // 1 governance token per 100 tokens staked/compounded
        uint256 govTokens = amount / 100;
        
        if (govTokens > 0) {
            governanceBalance[user] += govTokens;
            emit GovernanceTokensEarned(user, govTokens);
        }
    }
    
    /**
     * @notice Claim governance tokens
     */
    function claimGovernanceTokens() external nonReentrant {
        uint256 amount = governanceBalance[msg.sender];
        require(amount > 0, "No governance tokens");
        
        governanceBalance[msg.sender] = 0;
        
        require(governanceToken.transfer(msg.sender, amount), "Transfer failed");
    }
    
    // ============================================
    // ANALYTICS & STATS
    // ============================================
    
    /**
     * @notice Get detailed user statistics
     */
    function getUserDetailedStats(address user) external view returns (
        uint256 totalStaked,
        uint256 pendingRewards,
        uint256 totalRewardsClaimed,
        uint256 referralCount,
        uint256 referralRewards,
        bool autoCompoundEnabled,
        uint256 governanceTokens,
        uint256[] memory activePools
    ) {
        return (
            stakes[user].amount,
            calculateRewardsWithBonus(user),
            stakes[user].totalRewardsClaimed,
            referralData[user].totalReferred,
            referralData[user].referralRewards,
            autoCompoundSettings[user].enabled,
            governanceBalance[user],
            userActivePools[user]
        );
    }
    
    /**
     * @notice Get global platform statistics
     */
    function getGlobalStats() external view returns (
        uint256 totalValueLocked,
        uint256 totalUsers,
        uint256 totalRewardsDistributed,
        uint256 totalReferrals,
        uint256 autoCompoundUsers
    ) {
        uint256 tvl = 0;
        uint256 autoCompoundCount = 0;
        
        for (uint256 i = 0; i < pools.length; i++) {
            tvl += pools[i].totalStaked;
        }
        
        // Note: totalUsers and other stats would need to be tracked separately
        // This is a simplified version
        
        return (tvl, 0, 0, 0, autoCompoundCount);
    }
    
    // ============================================
    // ADMIN FUNCTIONS
    // ============================================
    
    /**
     * @notice Update referral bonus APY
     */
    function setReferralBonusAPY(uint256 referrerBonus, uint256 referredBonus) external onlyOwner {
        require(referrerBonus <= 1000, "Bonus too high"); // Max 10%
        require(referredBonus <= 500, "Bonus too high"); // Max 5%
        
        referralBonusAPY = referrerBonus;
        referredBonusAPY = referredBonus;
    }
    
    /**
     * @notice Update auto-compound fee
     */
    function setAutoCompoundFee(uint256 fee) external onlyOwner {
        require(fee <= 100, "Fee too high"); // Max 1%
        autoCompoundFee = fee;
    }
    
    /**
     * @notice Fund governance token pool
     */
    function fundGovernancePool(uint256 amount) external onlyOwner {
        require(governanceToken.transferFrom(msg.sender, address(this), amount), "Transfer failed");
    }
    
    // ============================================
    // OVERRIDE: Enhanced stake function
    // ============================================
    
    /**
     * @notice Override stake to add referral rewards
     */
    function stake(uint256 amount, uint256 poolId) public override nonReentrant whenNotPaused {
        super.stake(amount, poolId);
        
        // Add referral reward for referrer
        address referrer = referralData[msg.sender].referrer;
        if (referrer != address(0)) {
            uint256 referralReward = (amount * 100) / 10000; // 1% of staked amount
            referralData[referrer].referralRewards += referralReward;
        }
        
        // Earn governance tokens
        _earnGovernanceTokens(msg.sender, amount);
    }
}
