/**
 * ============================================
 * 🏛️ GOVERNANCE SYSTEM DEPLOYMENT SCRIPT
 * ============================================
 * 
 * This script deploys the complete governance system:
 * 1. GovernanceToken (GOVSPHE) - ERC20Votes token
 * 2. SpheraTimelock - 2-day timelock controller
 * 3. SpheraGovernor - Full DAO governance
 * 
 * Usage:
 *   npx hardhat run scripts/deploy-governance.js --network localhost
 *   npx hardhat run scripts/deploy-governance.js --network sepolia
 *   npx hardhat run scripts/deploy-governance.js --network mainnet
 * 
 * After deployment:
 * - Timelock will control all governance actions
 * - Governor can propose and execute proposals
 * - Token holders can vote on proposals
 * - 2-day delay before execution
 */

const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

// Configuration
const CONFIG = {
    // Governance Parameters
    VOTING_DELAY: 1 * 24 * 60 * 60,        // 1 day in seconds
    VOTING_PERIOD: 7 * 24 * 60 * 60,       // 7 days in seconds
    TIMELOCK_DELAY: 2 * 24 * 60 * 60,      // 2 days in seconds
    PROPOSAL_THRESHOLD: "1000",             // 1000 GOVSPHE tokens
    QUORUM_PERCENTAGE: 4,                   // 4% of total supply
    
    // Initial Token Distribution (optional)
    INITIAL_MINT: true,
    INITIAL_HOLDERS: [
        // { address: "0x...", amount: "1000" }
    ],
    
    // Verification
    VERIFY_ON_ETHERSCAN: true,
    WAIT_CONFIRMATIONS: 5,
};

/**
 * Main deployment function
 */
async function main() {
    console.log("\n============================================");
    console.log("🏛️  SPHERA GOVERNANCE DEPLOYMENT");
    console.log("============================================\n");
    
    const [deployer] = await hre.ethers.getSigners();
    console.log("📝 Deploying contracts with account:", deployer.address);
    
    const balance = await hre.ethers.provider.getBalance(deployer.address);
    console.log("💰 Account balance:", hre.ethers.formatEther(balance), "ETH\n");
    
    const deploymentInfo = {
        network: hre.network.name,
        deployer: deployer.address,
        timestamp: new Date().toISOString(),
        contracts: {},
        parameters: CONFIG,
    };
    
    // ============================================
    // STEP 1: Deploy GovernanceToken
    // ============================================
    
    console.log("============================================");
    console.log("📦 STEP 1: Deploying GovernanceToken...");
    console.log("============================================\n");
    
    const GovernanceToken = await hre.ethers.getContractFactory("GovernanceToken");
    const governanceToken = await GovernanceToken.deploy(deployer.address);
    await governanceToken.waitForDeployment();
    
    const tokenAddress = await governanceToken.getAddress();
    console.log("✅ GovernanceToken deployed to:", tokenAddress);
    
    deploymentInfo.contracts.governanceToken = {
        address: tokenAddress,
        name: await governanceToken.name(),
        symbol: await governanceToken.symbol(),
        totalSupply: "0",
    };
    
    // Wait for confirmations
    if (CONFIG.WAIT_CONFIRMATIONS > 0) {
        console.log(`⏳ Waiting for ${CONFIG.WAIT_CONFIRMATIONS} confirmations...`);
        await governanceToken.deploymentTransaction().wait(CONFIG.WAIT_CONFIRMATIONS);
        console.log("✅ Confirmations received\n");
    }
    
    // ============================================
    // STEP 2: Deploy SpheraTimelock
    // ============================================
    
    console.log("============================================");
    console.log("📦 STEP 2: Deploying SpheraTimelock...");
    console.log("============================================\n");
    
    const Timelock = await hre.ethers.getContractFactory("SpheraTimelock");
    const timelock = await Timelock.deploy(
        CONFIG.TIMELOCK_DELAY,
        [], // proposers (will be set to Governor later)
        [hre.ethers.ZeroAddress], // executors (anyone can execute)
        deployer.address // temporary admin (will be revoked later)
    );
    await timelock.waitForDeployment();
    
    const timelockAddress = await timelock.getAddress();
    console.log("✅ SpheraTimelock deployed to:", timelockAddress);
    console.log("   ⏱️  Min Delay:", CONFIG.TIMELOCK_DELAY, "seconds (", CONFIG.TIMELOCK_DELAY / 86400, "days)");
    
    deploymentInfo.contracts.timelock = {
        address: timelockAddress,
        minDelay: CONFIG.TIMELOCK_DELAY,
    };
    
    if (CONFIG.WAIT_CONFIRMATIONS > 0) {
        console.log(`⏳ Waiting for ${CONFIG.WAIT_CONFIRMATIONS} confirmations...`);
        await timelock.deploymentTransaction().wait(CONFIG.WAIT_CONFIRMATIONS);
        console.log("✅ Confirmations received\n");
    }
    
    // ============================================
    // STEP 3: Deploy SpheraGovernor
    // ============================================
    
    console.log("============================================");
    console.log("📦 STEP 3: Deploying SpheraGovernor...");
    console.log("============================================\n");
    
    const Governor = await hre.ethers.getContractFactory("SpheraGovernor");
    const governor = await Governor.deploy(
        tokenAddress,
        timelockAddress
    );
    await governor.waitForDeployment();
    
    const governorAddress = await governor.getAddress();
    console.log("✅ SpheraGovernor deployed to:", governorAddress);
    console.log("   🗳️  Voting Delay:", await governor.votingDelay(), "seconds");
    console.log("   🗳️  Voting Period:", await governor.votingPeriod(), "seconds");
    console.log("   🎫 Proposal Threshold:", hre.ethers.formatEther(await governor.proposalThreshold()), "GOVSPHE");
    console.log("   📊 Quorum:", await governor.quorumNumerator(), "%\n");
    
    deploymentInfo.contracts.governor = {
        address: governorAddress,
        votingDelay: Number(await governor.votingDelay()),
        votingPeriod: Number(await governor.votingPeriod()),
        proposalThreshold: hre.ethers.formatEther(await governor.proposalThreshold()),
        quorum: Number(await governor.quorumNumerator()),
    };
    
    if (CONFIG.WAIT_CONFIRMATIONS > 0) {
        console.log(`⏳ Waiting for ${CONFIG.WAIT_CONFIRMATIONS} confirmations...`);
        await governor.deploymentTransaction().wait(CONFIG.WAIT_CONFIRMATIONS);
        console.log("✅ Confirmations received\n");
    }
    
    // ============================================
    // STEP 4: Configure Roles & Permissions
    // ============================================
    
    console.log("============================================");
    console.log("🔐 STEP 4: Configuring Roles & Permissions...");
    console.log("============================================\n");
    
    const PROPOSER_ROLE = await timelock.PROPOSER_ROLE();
    const CANCELLER_ROLE = await timelock.CANCELLER_ROLE();
    const EXECUTOR_ROLE = await timelock.EXECUTOR_ROLE();
    const ADMIN_ROLE = await timelock.DEFAULT_ADMIN_ROLE();
    
    console.log("📝 Granting PROPOSER_ROLE to Governor...");
    let tx = await timelock.grantRole(PROPOSER_ROLE, governorAddress);
    await tx.wait();
    console.log("✅ PROPOSER_ROLE granted\n");
    
    console.log("📝 Granting CANCELLER_ROLE to Governor...");
    tx = await timelock.grantRole(CANCELLER_ROLE, governorAddress);
    await tx.wait();
    console.log("✅ CANCELLER_ROLE granted\n");
    
    console.log("📝 Granting ADMIN_ROLE to Timelock (self-admin)...");
    tx = await timelock.grantRole(ADMIN_ROLE, timelockAddress);
    await tx.wait();
    console.log("✅ ADMIN_ROLE granted to Timelock\n");
    
    console.log("📝 Revoking ADMIN_ROLE from deployer...");
    tx = await timelock.revokeRole(ADMIN_ROLE, deployer.address);
    await tx.wait();
    console.log("✅ ADMIN_ROLE revoked from deployer\n");
    console.log("🔒 Timelock is now self-administered!\n");
    
    // ============================================
    // STEP 5: Setup GovernanceToken
    // ============================================
    
    console.log("============================================");
    console.log("🪙 STEP 5: Setting up GovernanceToken...");
    console.log("============================================\n");
    
    // Add staking contract as minter (if address provided)
    // For now, keep deployer as minter for initial distribution
    console.log("📝 Current minters:");
    console.log("   - Deployer:", await governanceToken.minters(deployer.address));
    
    // Initial token distribution (if configured)
    if (CONFIG.INITIAL_MINT && CONFIG.INITIAL_HOLDERS.length > 0) {
        console.log("\n📝 Minting initial token distribution...");
        
        for (const holder of CONFIG.INITIAL_HOLDERS) {
            const amount = hre.ethers.parseEther(holder.amount);
            tx = await governanceToken.mint(holder.address, amount);
            await tx.wait();
            console.log(`   ✅ Minted ${holder.amount} GOVSPHE to ${holder.address}`);
        }
        
        const totalSupply = await governanceToken.totalSupply();
        console.log(`\n📊 Total Supply: ${hre.ethers.formatEther(totalSupply)} GOVSPHE`);
        deploymentInfo.contracts.governanceToken.totalSupply = hre.ethers.formatEther(totalSupply);
    }
    
    console.log("\n⚠️  Note: Deployer still has minter role for initial setup");
    console.log("   Remember to revoke or transfer this role after distribution!\n");
    
    // ============================================
    // STEP 6: Verify Contracts (optional)
    // ============================================
    
    if (CONFIG.VERIFY_ON_ETHERSCAN && hre.network.name !== "hardhat" && hre.network.name !== "localhost") {
        console.log("============================================");
        console.log("🔍 STEP 6: Verifying Contracts on Etherscan...");
        console.log("============================================\n");
        
        console.log("⏳ Waiting 30 seconds before verification...");
        await new Promise(resolve => setTimeout(resolve, 30000));
        
        try {
            console.log("📝 Verifying GovernanceToken...");
            await hre.run("verify:verify", {
                address: tokenAddress,
                constructorArguments: [deployer.address],
            });
            console.log("✅ GovernanceToken verified\n");
        } catch (error) {
            console.log("❌ GovernanceToken verification failed:", error.message, "\n");
        }
        
        try {
            console.log("📝 Verifying SpheraTimelock...");
            await hre.run("verify:verify", {
                address: timelockAddress,
                constructorArguments: [
                    CONFIG.TIMELOCK_DELAY,
                    [],
                    [hre.ethers.ZeroAddress],
                    deployer.address,
                ],
            });
            console.log("✅ SpheraTimelock verified\n");
        } catch (error) {
            console.log("❌ SpheraTimelock verification failed:", error.message, "\n");
        }
        
        try {
            console.log("📝 Verifying SpheraGovernor...");
            await hre.run("verify:verify", {
                address: governorAddress,
                constructorArguments: [tokenAddress, timelockAddress],
            });
            console.log("✅ SpheraGovernor verified\n");
        } catch (error) {
            console.log("❌ SpheraGovernor verification failed:", error.message, "\n");
        }
    }
    
    // ============================================
    // STEP 7: Save Deployment Info
    // ============================================
    
    console.log("============================================");
    console.log("💾 STEP 7: Saving Deployment Info...");
    console.log("============================================\n");
    
    const deploymentsDir = path.join(__dirname, "..", "deployments");
    if (!fs.existsSync(deploymentsDir)) {
        fs.mkdirSync(deploymentsDir, { recursive: true });
    }
    
    const filename = `governance-${hre.network.name}-${Date.now()}.json`;
    const filepath = path.join(deploymentsDir, filename);
    
    fs.writeFileSync(filepath, JSON.stringify(deploymentInfo, null, 2));
    console.log("✅ Deployment info saved to:", filepath);
    
    // Also update latest deployment
    const latestPath = path.join(deploymentsDir, `governance-${hre.network.name}-latest.json`);
    fs.writeFileSync(latestPath, JSON.stringify(deploymentInfo, null, 2));
    console.log("✅ Latest deployment updated:", latestPath, "\n");
    
    // ============================================
    // SUMMARY
    // ============================================
    
    console.log("\n============================================");
    console.log("🎉 DEPLOYMENT COMPLETE!");
    console.log("============================================\n");
    
    console.log("📋 Contract Addresses:");
    console.log("   GovernanceToken:", tokenAddress);
    console.log("   SpheraTimelock:", timelockAddress);
    console.log("   SpheraGovernor:", governorAddress);
    
    console.log("\n📊 Governance Parameters:");
    console.log("   Voting Delay:", CONFIG.VOTING_DELAY / 86400, "days");
    console.log("   Voting Period:", CONFIG.VOTING_PERIOD / 86400, "days");
    console.log("   Timelock Delay:", CONFIG.TIMELOCK_DELAY / 86400, "days");
    console.log("   Proposal Threshold:", CONFIG.PROPOSAL_THRESHOLD, "GOVSPHE");
    console.log("   Quorum:", CONFIG.QUORUM_PERCENTAGE, "%");
    
    console.log("\n🔑 Next Steps:");
    console.log("   1. Distribute governance tokens to stakeholders");
    console.log("   2. Add staking contract as minter (if applicable)");
    console.log("   3. Revoke deployer minter role");
    console.log("   4. Create your first proposal!");
    console.log("   5. Test the governance flow");
    
    console.log("\n📚 Useful Commands:");
    console.log("   Create proposal:");
    console.log("     npx hardhat run scripts/create-proposal-example.js --network", hre.network.name);
    console.log("\n   Run governance tests:");
    console.log("     npx hardhat test test/Governance.test.js");
    
    console.log("\n============================================\n");
    
    return {
        governanceToken: tokenAddress,
        timelock: timelockAddress,
        governor: governorAddress,
    };
}

// Execute deployment
main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("\n❌ Deployment failed:");
        console.error(error);
        process.exit(1);
    });
