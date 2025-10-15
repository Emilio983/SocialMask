const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
  console.log("=============================================");
  console.log("  thesocialmask SURVEY ESCROW DEPLOYMENT");
  console.log("=============================================\n");

  const [deployer] = await hre.ethers.getSigners();
  const network = hre.network.name;

  console.log("Network:", network);
  console.log("Deployer address:", deployer.address);
  console.log("Deployer balance:", hre.ethers.formatEther(await hre.ethers.provider.getBalance(deployer.address)), "MATIC\n");

  // SPHE Token address on Polygon
  const SPHE_TOKEN_ADDRESS = process.env.SPHE_TOKEN_ADDRESS || "0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b";

  console.log("SPHE Token Address:", SPHE_TOKEN_ADDRESS);
  console.log("\n📝 Deploying SurveyEscrow contract...\n");

  // Deploy SurveyEscrow
  const SurveyEscrow = await hre.ethers.getContractFactory("SurveyEscrow");
  const surveyEscrow = await SurveyEscrow.deploy(SPHE_TOKEN_ADDRESS);

  await surveyEscrow.waitForDeployment();

  const escrowAddress = await surveyEscrow.getAddress();

  console.log("✅ SurveyEscrow deployed to:", escrowAddress);
  console.log("\n=============================================");
  console.log("  DEPLOYMENT SUCCESSFUL!");
  console.log("=============================================\n");

  console.log("📋 Contract Details:");
  console.log("- Contract: SurveyEscrow");
  console.log("- Address:", escrowAddress);
  console.log("- Network:", network);
  console.log("- SPHE Token:", SPHE_TOKEN_ADDRESS);
  console.log("- Owner:", deployer.address);
  console.log("\n");

  // Save deployment info
  const deploymentInfo = {
    network: network,
    chainId: hre.network.config.chainId,
    contractAddress: escrowAddress,
    spheTokenAddress: SPHE_TOKEN_ADDRESS,
    deployer: deployer.address,
    deployedAt: new Date().toISOString(),
    blockNumber: await hre.ethers.provider.getBlockNumber()
  };

  const deploymentsDir = path.join(__dirname, "../deployments");
  if (!fs.existsSync(deploymentsDir)) {
    fs.mkdirSync(deploymentsDir);
  }

  const deploymentFile = path.join(deploymentsDir, `${network}.json`);
  fs.writeFileSync(deploymentFile, JSON.stringify(deploymentInfo, null, 2));

  console.log("💾 Deployment info saved to:", deploymentFile);
  console.log("\n");

  // Update PHP config
  console.log("📝 Next steps:");
  console.log("1. Update blockchain_config.php with:");
  console.log(`   define('ESCROW_CONTRACT_ADDRESS', '${escrowAddress}');`);
  console.log("\n2. Update frontend/surveys.php CONFIG.ESCROW_CONTRACT with:");
  console.log(`   ESCROW_CONTRACT: '${escrowAddress}'`);
  console.log("\n3. Run SQL to create tables:");
  console.log("   mysql -u user -p database < database/escrow_schema.sql");
  console.log("\n4. Setup cron jobs in cPanel:");
  console.log("   */1 * * * * /usr/bin/php /path/to/cron/verify_pending_payments.php");
  console.log("   */5 * * * * /usr/bin/php /path/to/cron/check_survey_finalization.php");
  console.log("\n");

  if (network !== "localhost" && network !== "hardhat") {
    console.log("⏳ Waiting 30 seconds before verification...\n");
    await new Promise(resolve => setTimeout(resolve, 30000));

    console.log("🔍 Verifying contract on PolygonScan...\n");
    try {
      await hre.run("verify:verify", {
        address: escrowAddress,
        constructorArguments: [SPHE_TOKEN_ADDRESS],
      });
      console.log("✅ Contract verified successfully!");
    } catch (error) {
      console.log("⚠️ Verification failed:", error.message);
      console.log("You can verify manually later with:");
      console.log(`npx hardhat verify --network ${network} ${escrowAddress} ${SPHE_TOKEN_ADDRESS}`);
    }
  }

  console.log("\n=============================================\n");
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
