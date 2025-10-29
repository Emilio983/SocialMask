const { ethers } = require("hardhat");
const fs = require("fs");
const path = require("path");

/**
 * ============================================
 * GOVERNANCE SYSTEM DEPLOYMENT SCRIPT
 * ============================================
 * Deploys all 7 governance contracts in the correct order
 */

async function main() {
    console.log("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log("🚀 DEPLOYING GOVERNANCE SYSTEM");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

    const [deployer] = await ethers.getSigners();
    console.log("📍 Deploying from:", deployer.address);
    console.log("💰 Balance:", ethers.utils.formatEther(await deployer.getBalance()), "ETH\n");

    const deployedContracts = {};
    const deploymentInfo = {
        network: network.name,
        deployer: deployer.address,
        timestamp: new Date().toISOString(),
        contracts: {}
    };

    // ============================================
    // 1. DEPLOY GOVERNANCE TOKEN (if needed)
    // ============================================
    console.log("1️⃣  Deploying Governance Token...");
    
    // Replace with your actual token or use existing
    const TOKEN_ADDRESS = process.env.GOVERNANCE_TOKEN || null;
    
    let governanceToken;
    if (TOKEN_ADDRESS) {
        console.log("   Using existing token:", TOKEN_ADDRESS);
        governanceToken = TOKEN_ADDRESS;
    } else {
        // Deploy mock token for testing
        const Token = await ethers.getContractFactory("MockERC20");
        const token = await Token.deploy("Sphera Governance", "SPHERA", ethers.utils.parseEther("1000000"));
        await token.deployed();
        governanceToken = token.address;
        console.log("   ✅ Token deployed:", governanceToken);
    }
    
    deployedContracts.governanceToken = governanceToken;
    deploymentInfo.contracts.governanceToken = governanceToken;

    // ============================================
    // 2. DEPLOY TIMELOCK CONTROLLER
    // ============================================
    console.log("\n2️⃣  Deploying Timelock Controller...");
    
    const TimelockController = await ethers.getContractFactory("TimelockController");
    const timelock = await TimelockController.deploy(
        172800, // 48 hours in seconds
        [deployer.address], // proposers
        [ethers.constants.AddressZero], // executors (anyone)
        deployer.address // admin
    );
    await timelock.deployed();
    
    console.log("   ✅ Timelock deployed:", timelock.address);
    console.log("   ⏱️  Delay: 48 hours");
    
    deployedContracts.timelock = timelock.address;
    deploymentInfo.contracts.timelock = {
        address: timelock.address,
        delay: "172800",
        delayHours: 48
    };

    // ============================================
    // 3. DEPLOY GASLESS VOTING
    // ============================================
    console.log("\n3️⃣  Deploying Gasless Voting...");
    
    const GaslessVoting = await ethers.getContractFactory("GaslessVoting");
    const gaslessVoting = await GaslessVoting.deploy(
        governanceToken,
        "Sphera Governance"
    );
    await gaslessVoting.deployed();
    
    console.log("   ✅ Gasless Voting deployed:", gaslessVoting.address);
    console.log("   🎫 EIP-712 Domain: Sphera Governance");
    
    deployedContracts.gaslessVoting = gaslessVoting.address;
    deploymentInfo.contracts.gaslessVoting = {
        address: gaslessVoting.address,
        token: governanceToken
    };

    // ============================================
    // 4. DEPLOY MULTI-SIGNATURE
    // ============================================
    console.log("\n4️⃣  Deploying Multi-Signature Governance...");
    
    // Initial signers (replace with actual addresses)
    const initialSigners = [
        deployer.address,
        // Add 4 more signers for production
        "0x0000000000000000000000000000000000000001",
        "0x0000000000000000000000000000000000000002",
        "0x0000000000000000000000000000000000000003",
        "0x0000000000000000000000000000000000000004"
    ];
    
    const MultiSigGovernance = await ethers.getContractFactory("MultiSigGovernance");
    const multiSig = await MultiSigGovernance.deploy(
        initialSigners,
        3 // 3 of 5 required
    );
    await multiSig.deployed();
    
    console.log("   ✅ Multi-Sig deployed:", multiSig.address);
    console.log("   ✍️  Required signatures: 3 of 5");
    
    deployedContracts.multiSig = multiSig.address;
    deploymentInfo.contracts.multiSig = {
        address: multiSig.address,
        requiredSignatures: 3,
        totalSigners: 5
    };

    // ============================================
    // 5. DEPLOY QUADRATIC VOTING
    // ============================================
    console.log("\n5️⃣  Deploying Quadratic Voting...");
    
    const QuadraticVoting = await ethers.getContractFactory("QuadraticVoting");
    const quadraticVoting = await QuadraticVoting.deploy(governanceToken);
    await quadraticVoting.deployed();
    
    console.log("   ✅ Quadratic Voting deployed:", quadraticVoting.address);
    console.log("   📊 Vote power = √(token balance)");
    
    deployedContracts.quadraticVoting = quadraticVoting.address;
    deploymentInfo.contracts.quadraticVoting = {
        address: quadraticVoting.address,
        token: governanceToken
    };

    // ============================================
    // 6. DEPLOY TREASURY MANAGEMENT
    // ============================================
    console.log("\n6️⃣  Deploying Treasury Management...");
    
    const TreasuryManagement = await ethers.getContractFactory("TreasuryManagement");
    const treasury = await TreasuryManagement.deploy();
    await treasury.deployed();
    
    console.log("   ✅ Treasury deployed:", treasury.address);
    console.log("   💰 Multi-token support enabled");
    console.log("   📤 Streaming payments enabled");
    
    deployedContracts.treasury = treasury.address;
    deploymentInfo.contracts.treasury = {
        address: treasury.address,
        features: ["multi-token", "streaming", "budgets", "multi-approval"]
    };

    // ============================================
    // 7. DEPLOY PROPOSAL TEMPLATES
    // ============================================
    console.log("\n7️⃣  Deploying Proposal Templates...");
    
    const ProposalTemplates = await ethers.getContractFactory("ProposalTemplates");
    const templates = await ProposalTemplates.deploy();
    await templates.deployed();
    
    console.log("   ✅ Templates deployed:", templates.address);
    console.log("   📋 7 default templates created");
    
    deployedContracts.templates = templates.address;
    deploymentInfo.contracts.templates = {
        address: templates.address,
        defaultTemplates: 7
    };

    // ============================================
    // 8. DEPLOY SNAPSHOT BRIDGE
    // ============================================
    console.log("\n8️⃣  Deploying Snapshot Bridge...");
    
    const snapshotSpace = process.env.SNAPSHOT_SPACE || "sphera.eth";
    
    const SnapshotBridge = await ethers.getContractFactory("SnapshotBridge");
    const snapshotBridge = await SnapshotBridge.deploy(snapshotSpace);
    await snapshotBridge.deployed();
    
    console.log("   ✅ Snapshot Bridge deployed:", snapshotBridge.address);
    console.log("   🔗 Space:", snapshotSpace);
    
    deployedContracts.snapshotBridge = snapshotBridge.address;
    deploymentInfo.contracts.snapshotBridge = {
        address: snapshotBridge.address,
        space: snapshotSpace
    };

    // ============================================
    // SAVE DEPLOYMENT INFO
    // ============================================
    console.log("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log("💾 SAVING DEPLOYMENT INFO");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

    const deploymentsDir = path.join(__dirname, "../deployments");
    if (!fs.existsSync(deploymentsDir)) {
        fs.mkdirSync(deploymentsDir, { recursive: true });
    }

    // Save full deployment info
    const deploymentFile = path.join(deploymentsDir, `${network.name}-${Date.now()}.json`);
    fs.writeFileSync(deploymentFile, JSON.stringify(deploymentInfo, null, 2));
    console.log("📄 Deployment info saved:", deploymentFile);

    // Save addresses only (for easy import)
    const addressesFile = path.join(deploymentsDir, `${network.name}-addresses.json`);
    fs.writeFileSync(addressesFile, JSON.stringify(deployedContracts, null, 2));
    console.log("📄 Addresses saved:", addressesFile);

    // ============================================
    // SUMMARY
    // ============================================
    console.log("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log("✅ DEPLOYMENT COMPLETE");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    
    console.log("📋 Deployed Contracts:");
    console.log("   Governance Token:", deployedContracts.governanceToken);
    console.log("   Timelock:", deployedContracts.timelock);
    console.log("   Gasless Voting:", deployedContracts.gaslessVoting);
    console.log("   Multi-Signature:", deployedContracts.multiSig);
    console.log("   Quadratic Voting:", deployedContracts.quadraticVoting);
    console.log("   Treasury:", deployedContracts.treasury);
    console.log("   Templates:", deployedContracts.templates);
    console.log("   Snapshot Bridge:", deployedContracts.snapshotBridge);
    
    console.log("\n🔗 Next Steps:");
    console.log("   1. Verify contracts on Etherscan");
    console.log("   2. Update frontend with new addresses");
    console.log("   3. Run integration tests");
    console.log("   4. Grant roles and permissions");
    console.log("   5. Transfer ownership to DAO");
    
    console.log("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

    return deployedContracts;
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("\n❌ Deployment failed:");
        console.error(error);
        process.exit(1);
    });
