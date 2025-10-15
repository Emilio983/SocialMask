const { expect } = require("chai");
const { ethers } = require("hardhat");
const { time } = require("@nomicfoundation/hardhat-network-helpers");

describe("TokenStaking", function () {
    let tokenStaking;
    let stakingToken;
    let owner;
    let user1;
    let user2;
    let user3;

    const INITIAL_SUPPLY = ethers.parseEther("10000000"); // 10M tokens
    const REWARD_RATE_PER_SECOND = ethers.parseEther("0.0001"); // 0.0001 tokens per second
    const MIN_STAKE = ethers.parseEther("10"); // 10 tokens
    const STAKE_AMOUNT = ethers.parseEther("1000"); // 1000 tokens
    const REWARD_POOL_AMOUNT = ethers.parseEther("100000"); // 100k tokens for rewards

    beforeEach(async function () {
        [owner, user1, user2, user3] = await ethers.getSigners();

        // Deploy mock ERC20 token
        const MockToken = await ethers.getContractFactory("contracts/MockERC20.sol:MockERC20");
        stakingToken = await MockToken.deploy("TheSocialMask Token", "SPHE", INITIAL_SUPPLY);
        await stakingToken.waitForDeployment();

        // Deploy TokenStaking
        const TokenStaking = await ethers.getContractFactory("TokenStaking");
        tokenStaking = await TokenStaking.deploy(
            await stakingToken.getAddress(),
            REWARD_RATE_PER_SECOND
        );
        await tokenStaking.waitForDeployment();

        // Distribute tokens
        await stakingToken.transfer(user1.address, ethers.parseEther("50000"));
        await stakingToken.transfer(user2.address, ethers.parseEther("50000"));
        await stakingToken.transfer(user3.address, ethers.parseEther("50000"));

        // Fund reward pool
        await stakingToken.approve(await tokenStaking.getAddress(), REWARD_POOL_AMOUNT);
        await tokenStaking.fundRewardPool(REWARD_POOL_AMOUNT);

        // Approve staking contract
        await stakingToken.connect(user1).approve(await tokenStaking.getAddress(), ethers.MaxUint256);
        await stakingToken.connect(user2).approve(await tokenStaking.getAddress(), ethers.MaxUint256);
        await stakingToken.connect(user3).approve(await tokenStaking.getAddress(), ethers.MaxUint256);
    });

    describe("Deployment", function () {
        it("Should set the correct staking token", async function () {
            expect(await tokenStaking.stakingToken()).to.equal(await stakingToken.getAddress());
        });

        it("Should set the correct reward rate", async function () {
            expect(await tokenStaking.rewardRatePerSecond()).to.equal(REWARD_RATE_PER_SECOND);
        });

        it("Should set the correct owner", async function () {
            expect(await tokenStaking.owner()).to.equal(owner.address);
        });

        it("Should create default flexible pool", async function () {
            const poolInfo = await tokenStaking.getPoolInfo(0);
            expect(poolInfo.name).to.equal("Flexible");
            expect(poolInfo.lockPeriod).to.equal(0);
            expect(poolInfo.active).to.be.true;
        });

        it("Should have reward pool funded", async function () {
            expect(await tokenStaking.rewardPool()).to.equal(REWARD_POOL_AMOUNT);
        });
    });

    describe("Staking", function () {
        it("Should stake tokens successfully", async function () {
            const blockTimestamp = await time.latest();
            
            await expect(tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0))
                .to.emit(tokenStaking, "Staked");

            const stakeInfo = await tokenStaking.stakes(user1.address);
            expect(stakeInfo.amount).to.equal(STAKE_AMOUNT);
            expect(await tokenStaking.isStaking(user1.address)).to.be.true;
            expect(await tokenStaking.totalStaked()).to.equal(STAKE_AMOUNT);
        });

        it("Should revert if staking amount is zero", async function () {
            await expect(
                tokenStaking.connect(user1).stake(0, 0)
            ).to.be.revertedWithCustomError(tokenStaking, "ZeroAmount");
        });

        it("Should revert if staking below minimum", async function () {
            await expect(
                tokenStaking.connect(user1).stake(ethers.parseEther("5"), 0)
            ).to.be.revertedWithCustomError(tokenStaking, "StakeBelowMinimum");
        });

        it("Should revert if staking in inactive pool", async function () {
            await tokenStaking.togglePool(0, false);
            await expect(
                tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0)
            ).to.be.revertedWithCustomError(tokenStaking, "PoolNotActive");
        });

        it("Should allow multiple stakes from same user", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            await time.increase(100);
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);

            const stakeInfo = await tokenStaking.stakes(user1.address);
            expect(stakeInfo.amount).to.equal(STAKE_AMOUNT * 2n);
        });

        it("Should allow multiple users to stake", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            await tokenStaking.connect(user2).stake(STAKE_AMOUNT, 0);
            await tokenStaking.connect(user3).stake(STAKE_AMOUNT, 0);

            expect(await tokenStaking.totalStaked()).to.equal(STAKE_AMOUNT * 3n);
        });

        it("Should transfer tokens to contract", async function () {
            const balanceBefore = await stakingToken.balanceOf(user1.address);
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            const balanceAfter = await stakingToken.balanceOf(user1.address);

            expect(balanceBefore - balanceAfter).to.equal(STAKE_AMOUNT);
        });
    });

    describe("Rewards Calculation", function () {
        it("Should calculate rewards correctly after time", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            
            const stakingDuration = 3600; // 1 hour
            await time.increase(stakingDuration);

            const expectedReward = (STAKE_AMOUNT * REWARD_RATE_PER_SECOND * BigInt(stakingDuration)) / ethers.parseEther("1");
            const calculatedReward = await tokenStaking.calculateRewards(user1.address);

            expect(calculatedReward).to.be.closeTo(expectedReward, ethers.parseEther("0.1"));
        });

        it("Should return zero rewards for non-stakers", async function () {
            expect(await tokenStaking.calculateRewards(user1.address)).to.equal(0);
        });

        it("Should accumulate rewards over time", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            
            await time.increase(1800); // 30 minutes
            const rewards1 = await tokenStaking.calculateRewards(user1.address);
            
            await time.increase(1800); // 30 more minutes
            const rewards2 = await tokenStaking.calculateRewards(user1.address);

            expect(rewards2).to.be.greaterThan(rewards1);
            expect(rewards2).to.be.closeTo(rewards1 * 2n, ethers.parseEther("0.1"));
        });

        it("Should calculate rewards for multiple pool multiplier", async function () {
            // Create pool with 2x multiplier
            await tokenStaking.createPool(1, "30 Days", 30 * 24 * 3600, 200, MIN_STAKE);
            
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 1);
            await time.increase(3600);

            const rewards = await tokenStaking.calculateRewards(user1.address);
            const baseReward = (STAKE_AMOUNT * REWARD_RATE_PER_SECOND * 3600n) / ethers.parseEther("1");
            const expectedReward = (baseReward * 200n) / 100n; // 2x multiplier

            expect(rewards).to.be.closeTo(expectedReward, ethers.parseEther("0.1"));
        });
    });

    describe("Claiming Rewards", function () {
        beforeEach(async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            await time.increase(3600); // 1 hour
        });

        it("Should claim rewards successfully", async function () {
            const rewardsBefore = await tokenStaking.calculateRewards(user1.address);
            const balanceBefore = await stakingToken.balanceOf(user1.address);

            await expect(tokenStaking.connect(user1).claimRewards())
                .to.emit(tokenStaking, "RewardsClaimed");

            const balanceAfter = await stakingToken.balanceOf(user1.address);
            expect(balanceAfter - balanceBefore).to.be.closeTo(rewardsBefore, ethers.parseEther("1"));
        });

        it("Should reset accumulated rewards after claim", async function () {
            await tokenStaking.connect(user1).claimRewards();
            
            const stakeInfo = await tokenStaking.stakes(user1.address);
            expect(stakeInfo.accumulatedRewards).to.equal(0);
        });

        it("Should update lastClaimTime after claim", async function () {
            const timeBefore = await time.latest();
            await tokenStaking.connect(user1).claimRewards();
            
            const stakeInfo = await tokenStaking.stakes(user1.address);
            expect(stakeInfo.lastClaimTime).to.be.greaterThan(timeBefore);
        });

        it("Should update totalRewardsClaimed", async function () {
            const rewardAmount = await tokenStaking.calculateRewards(user1.address);
            await tokenStaking.connect(user1).claimRewards();
            
            expect(await tokenStaking.totalRewardsClaimed(user1.address)).to.be.closeTo(
                rewardAmount,
                ethers.parseEther("1")
            );
        });

        it("Should revert if no stake found", async function () {
            await expect(
                tokenStaking.connect(user2).claimRewards()
            ).to.be.revertedWithCustomError(tokenStaking, "NoStakeFound");
        });
    });

    describe("Unstaking", function () {
        beforeEach(async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            await time.increase(3600);
        });

        it("Should unstake all tokens successfully", async function () {
            const balanceBefore = await stakingToken.balanceOf(user1.address);
            const rewards = await tokenStaking.calculateRewards(user1.address);

            await expect(tokenStaking.connect(user1).unstake(0))
                .to.emit(tokenStaking, "Unstaked");

            const balanceAfter = await stakingToken.balanceOf(user1.address);
            const received = balanceAfter - balanceBefore;

            expect(received).to.be.closeTo(STAKE_AMOUNT + rewards, ethers.parseEther("0.1"));
            expect(await tokenStaking.isStaking(user1.address)).to.be.false;
        });

        it("Should unstake partial amount", async function () {
            const unstakeAmount = ethers.parseEther("500");
            await tokenStaking.connect(user1).unstake(unstakeAmount);

            const stakeInfo = await tokenStaking.stakes(user1.address);
            expect(stakeInfo.amount).to.equal(STAKE_AMOUNT - unstakeAmount);
            expect(await tokenStaking.isStaking(user1.address)).to.be.true;
        });

        it("Should include rewards when unstaking", async function () {
            const balanceBefore = await stakingToken.balanceOf(user1.address);
            const rewards = await tokenStaking.calculateRewards(user1.address);

            await tokenStaking.connect(user1).unstake(0);

            const balanceAfter = await stakingToken.balanceOf(user1.address);
            expect(balanceAfter - balanceBefore).to.be.greaterThan(STAKE_AMOUNT);
        });

        it("Should revert if unstaking more than staked", async function () {
            await expect(
                tokenStaking.connect(user1).unstake(STAKE_AMOUNT + 1n)
            ).to.be.revertedWithCustomError(tokenStaking, "InsufficientStake");
        });

        it("Should revert if no stake found", async function () {
            await expect(
                tokenStaking.connect(user2).unstake(100)
            ).to.be.revertedWithCustomError(tokenStaking, "NoStakeFound");
        });

        it("Should update totalStaked after unstake", async function () {
            await tokenStaking.connect(user1).unstake(0);
            expect(await tokenStaking.totalStaked()).to.equal(0);
        });

        it("Should revert if still locked", async function () {
            // Create locked pool
            await tokenStaking.createPool(1, "30 Days", 30 * 24 * 3600, 150, MIN_STAKE);
            await tokenStaking.connect(user2).stake(STAKE_AMOUNT, 1);

            await time.increase(15 * 24 * 3600); // 15 days

            await expect(
                tokenStaking.connect(user2).unstake(0)
            ).to.be.revertedWithCustomError(tokenStaking, "StillLocked");
        });

        it("Should allow unstake after lock period", async function () {
            // Create locked pool
            await tokenStaking.createPool(1, "30 Days", 30 * 24 * 3600, 150, MIN_STAKE);
            
            // Fund LARGE reward pool for locked staking (31 days of rewards)
            const largeRewardPool = ethers.parseEther("1000000"); // 1M tokens
            await stakingToken.approve(await tokenStaking.getAddress(), largeRewardPool);
            await tokenStaking.fundRewardPool(largeRewardPool);
            
            await tokenStaking.connect(user2).stake(STAKE_AMOUNT, 1);

            await time.increase(31 * 24 * 3600); // 31 days

            await expect(tokenStaking.connect(user2).unstake(0))
                .to.emit(tokenStaking, "Unstaked");
        });
    });

    describe("Emergency Withdraw", function () {
        beforeEach(async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            await time.increase(3600);
        });

        it("Should emergency withdraw with fee", async function () {
            const balanceBefore = await stakingToken.balanceOf(user1.address);
            
            await expect(tokenStaking.connect(user1).emergencyWithdraw())
                .to.emit(tokenStaking, "EmergencyWithdraw");

            const balanceAfter = await stakingToken.balanceOf(user1.address);
            const received = balanceAfter - balanceBefore;

            // Should receive less than staked due to fee
            expect(received).to.be.lessThan(STAKE_AMOUNT);
            
            // Fee is 5% (500 basis points)
            const expectedFee = (STAKE_AMOUNT * 500n) / 10000n;
            const expectedReceived = STAKE_AMOUNT - expectedFee;
            expect(received).to.equal(expectedReceived);
        });

        it("Should not include rewards in emergency withdraw", async function () {
            const balanceBefore = await stakingToken.balanceOf(user1.address);
            await tokenStaking.connect(user1).emergencyWithdraw();
            const balanceAfter = await stakingToken.balanceOf(user1.address);

            // Should only receive stake minus fee, no rewards
            const fee = (STAKE_AMOUNT * 500n) / 10000n;
            expect(balanceAfter - balanceBefore).to.equal(STAKE_AMOUNT - fee);
        });

        it("Should add fee to reward pool", async function () {
            const rewardPoolBefore = await tokenStaking.rewardPool();
            await tokenStaking.connect(user1).emergencyWithdraw();
            const rewardPoolAfter = await tokenStaking.rewardPool();

            const expectedFee = (STAKE_AMOUNT * 500n) / 10000n;
            expect(rewardPoolAfter - rewardPoolBefore).to.equal(expectedFee);
        });

        it("Should delete stake info after emergency withdraw", async function () {
            await tokenStaking.connect(user1).emergencyWithdraw();
            expect(await tokenStaking.isStaking(user1.address)).to.be.false;
        });
    });

    describe("Multiple Pools", function () {
        beforeEach(async function () {
            // Create additional pools
            await tokenStaking.createPool(1, "30 Days", 30 * 24 * 3600, 150, MIN_STAKE);
            await tokenStaking.createPool(2, "90 Days", 90 * 24 * 3600, 200, MIN_STAKE);
            await tokenStaking.createPool(3, "180 Days", 180 * 24 * 3600, 250, MIN_STAKE);
        });

        it("Should create pools correctly", async function () {
            const pool1 = await tokenStaking.getPoolInfo(1);
            expect(pool1.name).to.equal("30 Days");
            expect(pool1.rewardMultiplier).to.equal(150);

            const pool2 = await tokenStaking.getPoolInfo(2);
            expect(pool2.name).to.equal("90 Days");
            expect(pool2.rewardMultiplier).to.equal(200);
        });

        it("Should stake in different pools", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            await tokenStaking.connect(user2).stake(STAKE_AMOUNT, 1);
            await tokenStaking.connect(user3).stake(STAKE_AMOUNT, 2);

            const stake1 = await tokenStaking.stakes(user1.address);
            const stake2 = await tokenStaking.stakes(user2.address);
            const stake3 = await tokenStaking.stakes(user3.address);

            expect(stake1.poolId).to.equal(0);
            expect(stake2.poolId).to.equal(1);
            expect(stake3.poolId).to.equal(2);
        });

        it("Should calculate different rewards for different pools", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0); // 1x multiplier
            await tokenStaking.connect(user2).stake(STAKE_AMOUNT, 2); // 2x multiplier

            await time.increase(3600);

            const rewards1 = await tokenStaking.calculateRewards(user1.address);
            const rewards2 = await tokenStaking.calculateRewards(user2.address);

            expect(rewards2).to.be.greaterThan(rewards1);
        });

        it("Should track total staked per pool", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 1);
            await tokenStaking.connect(user2).stake(STAKE_AMOUNT, 1);

            const poolInfo = await tokenStaking.getPoolInfo(1);
            expect(poolInfo.totalPoolStaked).to.equal(STAKE_AMOUNT * 2n);
        });
    });

    describe("Admin Functions", function () {
        it("Should update reward rate", async function () {
            const newRate = ethers.parseEther("0.0002");
            
            await expect(tokenStaking.updateRewardRate(newRate))
                .to.emit(tokenStaking, "RewardRateUpdated")
                .withArgs(REWARD_RATE_PER_SECOND, newRate, await time.latest() + 1);

            expect(await tokenStaking.rewardRatePerSecond()).to.equal(newRate);
        });

        it("Should fund reward pool", async function () {
            const addAmount = ethers.parseEther("10000");
            const poolBefore = await tokenStaking.rewardPool();

            await stakingToken.approve(await tokenStaking.getAddress(), addAmount);
            await tokenStaking.fundRewardPool(addAmount);

            expect(await tokenStaking.rewardPool()).to.equal(poolBefore + addAmount);
        });

        it("Should update early unstake fee", async function () {
            const newFee = 300; // 3%
            await tokenStaking.updateEarlyUnstakeFee(newFee);
            expect(await tokenStaking.earlyUnstakeFee()).to.equal(newFee);
        });

        it("Should revert if fee too high", async function () {
            await expect(
                tokenStaking.updateEarlyUnstakeFee(1500) // 15%
            ).to.be.revertedWith("Fee too high");
        });

        it("Should pause and unpause", async function () {
            await tokenStaking.pause();
            
            await expect(
                tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0)
            ).to.be.revertedWithCustomError(tokenStaking, "EnforcedPause");

            await tokenStaking.unpause();
            await expect(tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0))
                .to.emit(tokenStaking, "Staked");
        });

        it("Should toggle pool active status", async function () {
            await tokenStaking.togglePool(0, false);
            
            await expect(
                tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0)
            ).to.be.revertedWithCustomError(tokenStaking, "PoolNotActive");

            await tokenStaking.togglePool(0, true);
            await expect(tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0))
                .to.emit(tokenStaking, "Staked");
        });

        it("Should revert admin functions for non-owner", async function () {
            await expect(
                tokenStaking.connect(user1).updateRewardRate(100)
            ).to.be.revertedWithCustomError(tokenStaking, "OwnableUnauthorizedAccount");
        });
    });

    describe("APY Calculation", function () {
        it("Should calculate APY correctly", async function () {
            // Stake some tokens first to have totalStaked > 0
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            
            const apy = await tokenStaking.calculateAPY(0);
            
            // Expected APY calculation
            const yearlyRewardPerToken = REWARD_RATE_PER_SECOND * BigInt(365 * 24 * 3600);
            const expectedAPY = (yearlyRewardPerToken * 100n * 100n) / 100n;

            expect(apy).to.equal(expectedAPY);
        });

        it("Should return 0 APY for inactive pool", async function () {
            await tokenStaking.togglePool(0, false);
            expect(await tokenStaking.calculateAPY(0)).to.equal(0);
        });
    });

    describe("Get Stake Info", function () {
        it("Should return complete stake info", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            await time.increase(3600);

            const info = await tokenStaking.getStakeInfo(user1.address);
            
            expect(info.amount).to.equal(STAKE_AMOUNT);
            expect(info.poolId).to.equal(0);
            expect(info.pendingRewards).to.be.greaterThan(0);
            expect(info.canUnstake).to.be.true;
        });

        it("Should indicate cannot unstake when locked", async function () {
            await tokenStaking.createPool(1, "30 Days", 30 * 24 * 3600, 150, MIN_STAKE);
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 1);

            const info = await tokenStaking.getStakeInfo(user1.address);
            expect(info.canUnstake).to.be.false;
        });
    });

    describe("Edge Cases", function () {
        it("Should handle very small stakes", async function () {
            const smallStake = MIN_STAKE;
            await tokenStaking.connect(user1).stake(smallStake, 0);
            
            await time.increase(3600);
            const rewards = await tokenStaking.calculateRewards(user1.address);
            
            expect(rewards).to.be.greaterThan(0);
        });

        it("Should handle multiple consecutive stakes and unstakes", async function () {
            await tokenStaking.connect(user1).stake(STAKE_AMOUNT, 0);
            await time.increase(1000);
            
            await tokenStaking.connect(user1).unstake(ethers.parseEther("500"));
            await time.increase(1000);
            
            await tokenStaking.connect(user1).stake(ethers.parseEther("300"), 0);
            await time.increase(1000);
            
            const info = await tokenStaking.getStakeInfo(user1.address);
            expect(info.amount).to.be.greaterThan(0);
        });

        it("Should handle reward pool depletion", async function () {
            // Stake large amount
            const largeStake = ethers.parseEther("10000");
            await tokenStaking.connect(user1).stake(largeStake, 0);
            
            // Wait long time to accumulate rewards > pool
            await time.increase(365 * 24 * 3600 * 10); // 10 years
            
            await expect(
                tokenStaking.connect(user1).claimRewards()
            ).to.be.revertedWithCustomError(tokenStaking, "InsufficientRewardPool");
        });
    });
});
