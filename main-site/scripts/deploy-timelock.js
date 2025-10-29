/**
 * ============================================
 * DEPLOY TIMELOCK CONTROLLER
 * ============================================
 * Deploy script for TimelockController contract
 */

const hre = require("hardhat");

async function main() {
  console.log("🚀 Starting TimelockController deployment...\n");

  // Get deployer account
  const [deployer] = await hre.ethers.getSigners();
  console.log("📝 Deploying contracts with account:", deployer.address);
  console.log("💰 Account balance:", (await deployer.getBalance()).toString(), "\n");

  // Configuration
  const MIN_DELAY = 172800; // 48 hours in seconds
  const PROPOSERS = [deployer.address]; // Addresses that can propose
  const EXECUTORS = [deployer.address]; // Addresses that can execute
  const ADMIN = deployer.address; // Admin address

  console.log("⚙️  Configuration:");
  console.log("   MIN_DELAY:", MIN_DELAY, "seconds (48 hours)");
  console.log("   PROPOSERS:", PROPOSERS);
  console.log("   EXECUTORS:", EXECUTORS);
  console.log("   ADMIN:", ADMIN, "\n");

  // Deploy TimelockController
  console.log("📦 Deploying SpheraTimelockController...");
  const TimelockController = await hre.ethers.getContractFactory("SpheraTimelockController");
  const timelock = await TimelockController.deploy(
    MIN_DELAY,
    PROPOSERS,
    EXECUTORS,
    ADMIN
  );

  await timelock.deployed();

  console.log("✅ SpheraTimelockController deployed to:", timelock.address);
  console.log("🔗 Transaction hash:", timelock.deployTransaction.hash, "\n");

  // Wait for confirmations
  console.log("⏳ Waiting for confirmations...");
  await timelock.deployTransaction.wait(5);
  console.log("✅ Confirmed!\n");

  // Save deployment info
  const deploymentInfo = {
    network: hre.network.name,
    contractAddress: timelock.address,
    deployer: deployer.address,
    minDelay: MIN_DELAY,
    proposers: PROPOSERS,
    executors: EXECUTORS,
    admin: ADMIN,
    deploymentTime: new Date().toISOString(),
    transactionHash: timelock.deployTransaction.hash,
    blockNumber: timelock.deployTransaction.blockNumber
  };

  console.log("📄 Deployment Summary:");
  console.log(JSON.stringify(deploymentInfo, null, 2));
  console.log("\n");

  // Save to file
  const fs = require("fs");
  const path = require("path");
  const deploymentsDir = path.join(__dirname, "../deployments");
  
  if (!fs.existsSync(deploymentsDir)) {
    fs.mkdirSync(deploymentsDir, { recursive: true });
  }

  const filename = `timelock-${hre.network.name}-${Date.now()}.json`;
  fs.writeFileSync(
    path.join(deploymentsDir, filename),
    JSON.stringify(deploymentInfo, null, 2)
  );

  console.log("💾 Deployment info saved to:", filename);

  // Update config database
  console.log("\n📝 To update database config, run:");
  console.log(`   UPDATE governance_timelock_config`);
  console.log(`   SET config_value = '${timelock.address}'`);
  console.log(`   WHERE config_key = 'contract_address';`);

  // Verify contract (optional)
  if (hre.network.name !== "hardhat" && hre.network.name !== "localhost") {
    console.log("\n🔍 To verify contract on Etherscan, run:");
    console.log(`   npx hardhat verify --network ${hre.network.name} ${timelock.address} ${MIN_DELAY} "[${PROPOSERS}]" "[${EXECUTORS}]" ${ADMIN}`);
  }

  console.log("\n✅ Deployment complete!");
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error("❌ Deployment failed:", error);
    process.exit(1);
  });
