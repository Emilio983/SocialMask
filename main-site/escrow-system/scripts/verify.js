const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
  const network = hre.network.name;

  console.log("🔍 Verifying SurveyEscrow contract on", network);

  // Load deployment info
  const deploymentFile = path.join(__dirname, `../deployments/${network}.json`);

  if (!fs.existsSync(deploymentFile)) {
    console.error(`❌ No deployment found for network ${network}`);
    console.log(`Expected file: ${deploymentFile}`);
    process.exit(1);
  }

  const deployment = JSON.parse(fs.readFileSync(deploymentFile, "utf8"));

  console.log("Contract Address:", deployment.contractAddress);
  console.log("SPHE Token:", deployment.spheTokenAddress);

  try {
    await hre.run("verify:verify", {
      address: deployment.contractAddress,
      constructorArguments: [deployment.spheTokenAddress],
    });

    console.log("✅ Contract verified successfully!");
  } catch (error) {
    if (error.message.includes("Already Verified")) {
      console.log("ℹ️ Contract already verified");
    } else {
      console.error("❌ Verification failed:", error.message);
      process.exit(1);
    }
  }
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
