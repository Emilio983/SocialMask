const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
  console.log("=============================================");
  console.log("  thesocialmask MEMBERSHIP STAKING DEPLOYMENT");
  console.log("=============================================\n");

  const [deployer] = await hre.ethers.getSigners();
  const network = hre.network.name;

  console.log("Network:", network);
  console.log("Deployer address:", deployer.address);
  console.log("Deployer balance:", hre.ethers.formatEther(await hre.ethers.provider.getBalance(deployer.address)), "MATIC\n");

  // Configuración
  const SPHE_TOKEN_ADDRESS = process.env.SPHE_TOKEN_ADDRESS || "0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b";
  const TREASURY_WALLET = process.env.TREASURY_WALLET || "0xa1052872c755B5B2192b54ABD5F08546eeE6aa20";

  console.log("SPHE Token Address:", SPHE_TOKEN_ADDRESS);
  console.log("Treasury Wallet:", TREASURY_WALLET);
  console.log("\n📝 Deploying MembershipStaking contract...\n");

  // Deploy MembershipStaking
  const MembershipStaking = await hre.ethers.getContractFactory("MembershipStaking");
  const membershipStaking = await MembershipStaking.deploy(
    SPHE_TOKEN_ADDRESS,
    TREASURY_WALLET
  );

  await membershipStaking.waitForDeployment();

  const stakingAddress = await membershipStaking.getAddress();

  console.log("✅ MembershipStaking deployed to:", stakingAddress);
  console.log("\n=============================================");
  console.log("  DEPLOYMENT SUCCESSFUL!");
  console.log("=============================================\n");

  console.log("📋 Contract Details:");
  console.log("- Contract: MembershipStaking");
  console.log("- Address:", stakingAddress);
  console.log("- Network:", network);
  console.log("- SPHE Token:", SPHE_TOKEN_ADDRESS);
  console.log("- Treasury:", TREASURY_WALLET);
  console.log("- Owner:", deployer.address);
  console.log("\n");

  // Verificar configuración del contrato
  console.log("📊 Verifying contract configuration...\n");

  const platinumPrice = await membershipStaking.getPlanPrice("platinum");
  const goldPrice = await membershipStaking.getPlanPrice("gold");
  const diamondPrice = await membershipStaking.getPlanPrice("diamond");
  const creatorPrice = await membershipStaking.getPlanPrice("creator");

  console.log("Plan Prices:");
  console.log("- Platinum:", hre.ethers.formatEther(platinumPrice), "SPHE");
  console.log("- Gold:", hre.ethers.formatEther(goldPrice), "SPHE");
  console.log("- Diamond:", hre.ethers.formatEther(diamondPrice), "SPHE");
  console.log("- Creator:", hre.ethers.formatEther(creatorPrice), "SPHE");
  console.log("\n");

  // Save deployment info
  const deploymentInfo = {
    network: network,
    chainId: hre.network.config.chainId,
    contractAddress: stakingAddress,
    spheTokenAddress: SPHE_TOKEN_ADDRESS,
    treasuryAddress: TREASURY_WALLET,
    deployer: deployer.address,
    deployedAt: new Date().toISOString(),
    blockNumber: await hre.ethers.provider.getBlockNumber(),
    planPrices: {
      platinum: hre.ethers.formatEther(platinumPrice),
      gold: hre.ethers.formatEther(goldPrice),
      diamond: hre.ethers.formatEther(diamondPrice),
      creator: hre.ethers.formatEther(creatorPrice)
    }
  };

  const deploymentsDir = path.join(__dirname, "../deployments");
  if (!fs.existsSync(deploymentsDir)) {
    fs.mkdirSync(deploymentsDir);
  }

  const deploymentFile = path.join(deploymentsDir, `membership-staking-${network}.json`);
  fs.writeFileSync(deploymentFile, JSON.stringify(deploymentInfo, null, 2));

  console.log("💾 Deployment info saved to:", deploymentFile);
  console.log("\n");

  // Generate ABI file for frontend
  const artifactPath = path.join(__dirname, "../artifacts/contracts/MembershipStaking.sol/MembershipStaking.json");
  const artifact = JSON.parse(fs.readFileSync(artifactPath, 'utf8'));
  const abiFile = path.join(deploymentsDir, `membership-staking-abi.json`);
  fs.writeFileSync(abiFile, JSON.stringify(artifact.abi, null, 2));
  console.log("📄 ABI saved to:", abiFile);
  console.log("\n");

  // Update instructions
  console.log("📝 Next steps:");
  console.log("\n1. Update blockchain_config.php with:");
  console.log(`   define('MEMBERSHIP_STAKING_CONTRACT', '${stakingAddress}');`);

  console.log("\n2. Update membership.php CONSTANTS (around line 237):");
  console.log(`   const MEMBERSHIP_STAKING_CONTRACT = '${stakingAddress}';`);

  console.log("\n3. Copy the ABI from deployments/membership-staking-abi.json to membership.php");

  console.log("\n4. Update .env file:");
  console.log(`   MEMBERSHIP_STAKING_CONTRACT=${stakingAddress}`);

  console.log("\n5. Create API endpoints:");
  console.log("   - api/stakes/get_stakes.php");
  console.log("   - api/stakes/claim.php");

  console.log("\n6. Verify the table 'membership_stakes' exists in MySQL:");
  console.log("   mysql -u root -p thesocialmask -e \"DESCRIBE membership_stakes;\"");

  console.log("\n7. Test the staking flow:");
  console.log("   - Approve SPHE tokens to the staking contract");
  console.log("   - Call purchaseMembership(planType)");
  console.log("   - Verify stake is recorded in DB and blockchain");

  console.log("\n");

  if (network !== "localhost" && network !== "hardhat") {
    console.log("⏳ Waiting 30 seconds before verification...\n");
    await new Promise(resolve => setTimeout(resolve, 30000));

    console.log("🔍 Verifying contract on PolygonScan...\n");
    try {
      await hre.run("verify:verify", {
        address: stakingAddress,
        constructorArguments: [SPHE_TOKEN_ADDRESS, TREASURY_WALLET],
      });
      console.log("✅ Contract verified successfully!");
    } catch (error) {
      console.log("⚠️ Verification failed:", error.message);
      console.log("You can verify manually later with:");
      console.log(`npx hardhat verify --network ${network} ${stakingAddress} ${SPHE_TOKEN_ADDRESS} ${TREASURY_WALLET}`);
    }
  }

  console.log("\n=============================================");
  console.log("  DEPLOYMENT COMPLETE!");
  console.log("  Contract Address: " + stakingAddress);
  console.log("=============================================\n");
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
