/**
 * ============================================
 * 📝 CREATE PROPOSAL EXAMPLE SCRIPT
 * ============================================
 * 
 * This script demonstrates how to create governance proposals.
 * Includes templates for all 5 proposal categories.
 * 
 * Usage:
 *   npx hardhat run scripts/create-proposal-example.js --network localhost
 * 
 * Prerequisites:
 * - Governance system must be deployed
 * - Account must have >= 1000 GOVSPHE tokens
 * - Account must have delegated voting power to itself
 */

const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

// Load latest deployment
function loadDeployment(network) {
    const deploymentsDir = path.join(__dirname, "..", "deployments");
    const latestPath = path.join(deploymentsDir, `governance-${network}-latest.json`);
    
    if (!fs.existsSync(latestPath)) {
        throw new Error(`No deployment found for network: ${network}`);
    }
    
    return JSON.parse(fs.readFileSync(latestPath, "utf8"));
}

// Proposal Categories
const ProposalCategory = {
    ParameterChange: 0,      // Change APY, fees, limits, etc.
    TreasuryManagement: 1,   // Allocate treasury funds
    ContractUpgrade: 2,      // Upgrade smart contracts
    FeatureProposal: 3,      // Add new features
    EmergencyAction: 4,      // Emergency responses
};

/**
 * ============================================
 * PROPOSAL TEMPLATES
 * ============================================
 */

/**
 * Template 1: Parameter Change
 * Example: Change staking APY
 */
async function createParameterChangeProposal(governor, stakingAddress) {
    console.log("\n📝 Creating Parameter Change Proposal...\n");
    
    // Assuming TokenStaking has a function: setPoolAPY(uint256 poolId, uint256 newAPY)
    const StakingContract = await hre.ethers.getContractFactory("TokenStaking");
    const staking = StakingContract.attach(stakingAddress);
    
    const targets = [stakingAddress];
    const values = [0];
    const calldatas = [
        staking.interface.encodeFunctionData("setPoolAPY", [
            0, // Pool ID
            1500, // New APY: 15% (basis points)
        ])
    ];
    const description = `
# Proposal: Increase Flexible Pool APY to 15%

## Summary
This proposal suggests increasing the APY of the Flexible staking pool from 12% to 15% to attract more liquidity.

## Motivation
- Current APY is below market average
- Competitors offer 15-18% APY
- Increase in staking participation expected

## Specification
- Target: TokenStaking Contract
- Action: setPoolAPY(0, 1500)
- Pool: Flexible (ID: 0)
- New APY: 15%

## Impact
- Expected 30% increase in staked tokens
- Estimated cost: 50,000 SPHE per year
- Funding available in treasury

## Timeline
- Voting Period: 7 days
- Timelock Delay: 2 days
- Implementation: Immediate after execution

Category: Parameter Change
    `.trim();
    
    const category = ProposalCategory.ParameterChange;
    
    console.log("📋 Proposal Details:");
    console.log("   Target:", targets[0]);
    console.log("   Category: ParameterChange");
    console.log("   Action: Change APY to 15%");
    
    const tx = await governor.proposeWithCategory(
        targets,
        values,
        calldatas,
        description,
        category
    );
    
    const receipt = await tx.wait();
    const event = receipt.logs.find(log => {
        try {
            return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
        } catch { return false; }
    });
    
    const proposalId = governor.interface.parseLog(event).args[0];
    
    console.log("\n✅ Proposal Created!");
    console.log("   Proposal ID:", proposalId.toString());
    console.log("   Status: Pending (voting starts in 1 day)\n");
    
    return proposalId;
}

/**
 * Template 2: Treasury Management
 * Example: Allocate funds for marketing
 */
async function createTreasuryManagementProposal(governor, tokenAddress, treasuryAddress, recipientAddress) {
    console.log("\n📝 Creating Treasury Management Proposal...\n");
    
    const Token = await hre.ethers.getContractFactory("GovernanceToken");
    const token = Token.attach(tokenAddress);
    
    const amount = hre.ethers.parseEther("10000"); // 10,000 GOVSPHE
    
    const targets = [tokenAddress];
    const values = [0];
    const calldatas = [
        token.interface.encodeFunctionData("transfer", [
            recipientAddress,
            amount,
        ])
    ];
    const description = `
# Proposal: Allocate 10,000 GOVSPHE for Marketing Campaign

## Summary
Allocate 10,000 GOVSPHE tokens from treasury to fund Q1 2025 marketing campaign.

## Motivation
- Increase platform visibility
- Attract new users and liquidity providers
- Competitive marketing push needed

## Budget Breakdown
- Social Media Ads: 4,000 GOVSPHE
- Influencer Partnerships: 3,000 GOVSPHE
- Content Creation: 2,000 GOVSPHE
- Community Events: 1,000 GOVSPHE

## Recipient
Marketing Multi-sig: ${recipientAddress}

## Expected Results
- 50% increase in daily active users
- 100,000+ social media impressions
- 25% increase in total value locked

## Accountability
- Monthly progress reports
- Quarterly budget review
- Unused funds returned to treasury

Category: Treasury Management
    `.trim();
    
    const category = ProposalCategory.TreasuryManagement;
    
    console.log("📋 Proposal Details:");
    console.log("   Target:", targets[0]);
    console.log("   Category: TreasuryManagement");
    console.log("   Amount:", hre.ethers.formatEther(amount), "GOVSPHE");
    console.log("   Recipient:", recipientAddress);
    
    const tx = await governor.proposeWithCategory(
        targets,
        values,
        calldatas,
        description,
        category
    );
    
    const receipt = await tx.wait();
    const event = receipt.logs.find(log => {
        try {
            return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
        } catch { return false; }
    });
    
    const proposalId = governor.interface.parseLog(event).args[0];
    
    console.log("\n✅ Proposal Created!");
    console.log("   Proposal ID:", proposalId.toString(), "\n");
    
    return proposalId;
}

/**
 * Template 3: Feature Proposal
 * Example: Enable auto-compounding
 */
async function createFeatureProposal(governor, stakingAddress) {
    console.log("\n📝 Creating Feature Proposal...\n");
    
    // Assuming TokenStaking has: enableAutoCompound(bool enabled)
    const StakingContract = await hre.ethers.getContractFactory("TokenStaking");
    const staking = StakingContract.attach(stakingAddress);
    
    const targets = [stakingAddress];
    const values = [0];
    const calldatas = [
        staking.interface.encodeFunctionData("enableAutoCompound", [true])
    ];
    const description = `
# Proposal: Enable Auto-Compounding Feature

## Summary
Enable automatic compounding of staking rewards for all pools.

## Motivation
- User demand: 75% of survey respondents want auto-compound
- Improved user experience
- Increased capital efficiency
- Competitive feature (most platforms have it)

## Technical Specification
- Auto-compound frequency: Daily
- Gas optimization: Batch processing
- User opt-in/opt-out available
- No additional fees

## Benefits
- Higher effective APY for users
- Reduced manual transactions
- Improved token economics
- Reduced sell pressure

## Risks & Mitigation
- Gas costs: Funded by treasury
- Smart contract risk: Audited by CertiK
- User control: Opt-in by default

## Implementation Timeline
- Smart contract deployment: 1 week
- Testing period: 2 weeks
- Public launch: 3 weeks

Category: Feature Proposal
    `.trim();
    
    const category = ProposalCategory.FeatureProposal;
    
    console.log("📋 Proposal Details:");
    console.log("   Target:", targets[0]);
    console.log("   Category: FeatureProposal");
    console.log("   Feature: Auto-Compounding");
    
    const tx = await governor.proposeWithCategory(
        targets,
        values,
        calldatas,
        description,
        category
    );
    
    const receipt = await tx.wait();
    const event = receipt.logs.find(log => {
        try {
            return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
        } catch { return false; }
    });
    
    const proposalId = governor.interface.parseLog(event).args[0];
    
    console.log("\n✅ Proposal Created!");
    console.log("   Proposal ID:", proposalId.toString(), "\n");
    
    return proposalId;
}

/**
 * Template 4: Emergency Action
 * Example: Pause staking in case of vulnerability
 */
async function createEmergencyProposal(governor, stakingAddress) {
    console.log("\n📝 Creating Emergency Action Proposal...\n");
    
    // Assuming TokenStaking has: pause()
    const StakingContract = await hre.ethers.getContractFactory("TokenStaking");
    const staking = StakingContract.attach(stakingAddress);
    
    const targets = [stakingAddress];
    const values = [0];
    const calldatas = [
        staking.interface.encodeFunctionData("pause", [])
    ];
    const description = `
# EMERGENCY PROPOSAL: Pause Staking Contract

## ⚠️ CRITICAL SECURITY ALERT

## Situation
Potential vulnerability detected in staking contract reward calculation.

## Immediate Action Required
Pause all staking operations until security audit is complete.

## Details
- Issue: Potential reward manipulation
- Severity: HIGH
- Impact: Treasury funds at risk
- Discovery: White-hat security researcher report

## Proposed Action
1. Pause staking contract immediately
2. Existing stakes remain locked (funds SAFU)
3. No new stakes accepted
4. No withdrawals affected

## Next Steps
1. Emergency security audit (24-48 hours)
2. Deploy patched contract if needed
3. Resume operations after clearance
4. Post-mortem report to community

## User Impact
- Existing stakes: SAFE, locked as normal
- Withdrawals: UNAFFECTED
- New stakes: TEMPORARILY DISABLED
- Rewards: Continue accruing normally

## Accountability
- Security team: On standby
- Auditor: CertiK engaged
- Updates: Every 6 hours in Discord

Category: Emergency Action

**⚠️ This is a time-sensitive proposal. Please vote immediately.**
    `.trim();
    
    const category = ProposalCategory.EmergencyAction;
    
    console.log("📋 Proposal Details:");
    console.log("   Target:", targets[0]);
    console.log("   Category: EmergencyAction");
    console.log("   Action: Pause Contract");
    console.log("   ⚠️  URGENT: Security response");
    
    const tx = await governor.proposeWithCategory(
        targets,
        values,
        calldatas,
        description,
        category
    );
    
    const receipt = await tx.wait();
    const event = receipt.logs.find(log => {
        try {
            return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
        } catch { return false; }
    });
    
    const proposalId = governor.interface.parseLog(event).args[0];
    
    console.log("\n✅ Emergency Proposal Created!");
    console.log("   Proposal ID:", proposalId.toString(), "\n");
    
    return proposalId;
}

/**
 * Main function - Interactive proposal creation
 */
async function main() {
    console.log("\n============================================");
    console.log("📝 GOVERNANCE PROPOSAL CREATOR");
    console.log("============================================\n");
    
    // Load deployment info
    const deployment = loadDeployment(hre.network.name);
    console.log("📋 Loaded deployment for network:", hre.network.name);
    console.log("   Governor:", deployment.contracts.governor.address);
    console.log("   GovernanceToken:", deployment.contracts.governanceToken.address);
    console.log("   Timelock:", deployment.contracts.timelock.address, "\n");
    
    // Get signer
    const [proposer] = await hre.ethers.getSigners();
    console.log("👤 Proposer:", proposer.address);
    
    // Connect to contracts
    const governor = await hre.ethers.getContractAt(
        "SpheraGovernor",
        deployment.contracts.governor.address
    );
    
    const token = await hre.ethers.getContractAt(
        "GovernanceToken",
        deployment.contracts.governanceToken.address
    );
    
    // Check voting power
    const votingPower = await token.getVotes(proposer.address);
    const threshold = await governor.proposalThreshold();
    
    console.log("🗳️  Voting Power:", hre.ethers.formatEther(votingPower), "GOVSPHE");
    console.log("🎫 Threshold Required:", hre.ethers.formatEther(threshold), "GOVSPHE");
    
    if (votingPower < threshold) {
        console.log("\n❌ Insufficient voting power to create proposal!");
        console.log("   You need at least", hre.ethers.formatEther(threshold), "GOVSPHE");
        console.log("\n💡 To get voting power:");
        console.log("   1. Acquire GOVSPHE tokens (stake SPHE or receive from treasury)");
        console.log("   2. Delegate to yourself: token.selfDelegate()");
        return;
    }
    
    console.log("✅ Sufficient voting power to create proposals!\n");
    
    // ============================================
    // CREATE EXAMPLE PROPOSAL
    // ============================================
    
    console.log("============================================");
    console.log("📝 CREATING EXAMPLE PROPOSAL");
    console.log("============================================\n");
    
    console.log("Select proposal type:");
    console.log("  1. Parameter Change (Change staking APY)");
    console.log("  2. Treasury Management (Allocate marketing funds)");
    console.log("  3. Feature Proposal (Enable auto-compound)");
    console.log("  4. Emergency Action (Pause contract)");
    console.log("  5. Simple Test Proposal");
    
    // For this example, create a simple test proposal
    console.log("\n📝 Creating Simple Test Proposal...\n");
    
    const targets = [deployment.contracts.governanceToken.address];
    const values = [0];
    const calldatas = [
        token.interface.encodeFunctionData("transfer", [
            proposer.address,
            hre.ethers.parseEther("1") // Transfer 1 GOVSPHE back to proposer
        ])
    ];
    const description = `
# Test Proposal: Transfer 1 GOVSPHE

This is a test proposal to verify the governance system works correctly.

## Actions
- Transfer 1 GOVSPHE from timelock to proposer

## Purpose
- Test proposal creation
- Test voting mechanism
- Test execution flow

Category: Parameter Change
    `.trim();
    
    console.log("📋 Proposal Details:");
    console.log("   Target:", targets[0]);
    console.log("   Action: Transfer 1 GOVSPHE");
    console.log("   Category: ParameterChange");
    
    const tx = await governor.proposeWithCategory(
        targets,
        values,
        calldatas,
        description,
        ProposalCategory.ParameterChange
    );
    
    console.log("\n⏳ Waiting for transaction confirmation...");
    const receipt = await tx.wait();
    
    const event = receipt.logs.find(log => {
        try {
            return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
        } catch { return false; }
    });
    
    const proposalId = governor.interface.parseLog(event).args[0];
    
    console.log("\n============================================");
    console.log("✅ PROPOSAL CREATED SUCCESSFULLY!");
    console.log("============================================\n");
    
    console.log("📋 Proposal Information:");
    console.log("   Proposal ID:", proposalId.toString());
    console.log("   Proposer:", proposer.address);
    console.log("   Status: Pending");
    
    console.log("\n⏱️  Timeline:");
    console.log("   Voting starts in:", deployment.contracts.governor.votingDelay / 86400, "days");
    console.log("   Voting period:", deployment.contracts.governor.votingPeriod / 86400, "days");
    console.log("   Timelock delay:", deployment.contracts.timelock.minDelay / 86400, "days");
    
    console.log("\n🗳️  Next Steps:");
    console.log("   1. Wait for voting delay to pass");
    console.log("   2. Cast your vote: governor.castVote(proposalId, 1)");
    console.log("   3. Wait for voting period to end");
    console.log("   4. Queue the proposal: governor.queue(...)");
    console.log("   5. Wait for timelock delay");
    console.log("   6. Execute: governor.execute(...)");
    
    console.log("\n📊 Check proposal state:");
    console.log("   State:", await governor.state(proposalId));
    console.log("   (0=Pending, 1=Active, 2=Canceled, 3=Defeated, 4=Succeeded, 5=Queued, 6=Expired, 7=Executed)");
    
    console.log("\n============================================\n");
}

// Execute script
main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("\n❌ Error creating proposal:");
        console.error(error);
        process.exit(1);
    });
