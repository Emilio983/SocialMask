const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
  console.log("🚀 Desplegando GaslessActions...\n");

  // Obtener el deployer
  const [deployer] = await hre.ethers.getSigners();
  console.log("📝 Deployer address:", deployer.address);
  console.log("💰 Balance:", hre.ethers.formatEther(await hre.ethers.provider.getBalance(deployer.address)), "MATIC\n");

  // Direcciones necesarias
  const SPHE_TOKEN_ADDRESS = process.env.SPHE_TOKEN_ADDRESS || "0xYourSPHETokenAddress";
  const TREASURY_ADDRESS = process.env.TREASURY_ADDRESS || deployer.address; // Por defecto: deployer

  if (SPHE_TOKEN_ADDRESS === "0xYourSPHETokenAddress") {
    console.error("❌ Error: Debes configurar SPHE_TOKEN_ADDRESS en .env");
    process.exit(1);
  }

  console.log("🔧 Configuración:");
  console.log("   SPHE Token:", SPHE_TOKEN_ADDRESS);
  console.log("   Treasury:", TREASURY_ADDRESS);
  console.log("");

  // Deploy del contrato
  console.log("📦 Desplegando contrato GaslessActions...");
  const GaslessActions = await hre.ethers.getContractFactory("GaslessActions");
  const gaslessActions = await GaslessActions.deploy(SPHE_TOKEN_ADDRESS, TREASURY_ADDRESS);

  await gaslessActions.waitForDeployment();
  const gaslessActionsAddress = await gaslessActions.getAddress();

  console.log("✅ GaslessActions desplegado en:", gaslessActionsAddress);
  console.log("");

  // Verificar configuración inicial
  console.log("🔍 Verificando configuración inicial...");
  const platformFee = await gaslessActions.platformFee();
  const treasury = await gaslessActions.treasury();
  console.log("   Platform Fee:", platformFee.toString(), "basis points (", Number(platformFee) / 100, "%)");
  console.log("   Treasury:", treasury);
  console.log("");

  // Guardar deployment info
  const deploymentInfo = {
    network: hre.network.name,
    chainId: (await hre.ethers.provider.getNetwork()).chainId.toString(),
    contractAddress: gaslessActionsAddress,
    spheTokenAddress: SPHE_TOKEN_ADDRESS,
    treasury: TREASURY_ADDRESS,
    platformFee: platformFee.toString(),
    deployedAt: new Date().toISOString(),
    deployer: deployer.address,
    transactionHash: gaslessActions.deploymentTransaction().hash,
  };

  const deploymentsDir = path.join(__dirname, "../deployments");
  if (!fs.existsSync(deploymentsDir)) {
    fs.mkdirSync(deploymentsDir, { recursive: true });
  }

  const deploymentFile = path.join(deploymentsDir, `gasless-actions-${hre.network.name}.json`);
  fs.writeFileSync(deploymentFile, JSON.stringify(deploymentInfo, null, 2));
  console.log("💾 Deployment info guardado en:", deploymentFile);
  console.log("");

  // Guardar ABI para el frontend
  const artifactPath = path.join(__dirname, "../artifacts/contracts/GaslessActions.sol/GaslessActions.json");
  const artifact = JSON.parse(fs.readFileSync(artifactPath, "utf-8"));
  const abiDir = path.join(__dirname, "../abis");

  if (!fs.existsSync(abiDir)) {
    fs.mkdirSync(abiDir, { recursive: true });
  }

  const abiFile = path.join(abiDir, "GaslessActions.json");
  fs.writeFileSync(
    abiFile,
    JSON.stringify({
      address: gaslessActionsAddress,
      abi: artifact.abi,
    }, null, 2)
  );
  console.log("📄 ABI guardado en:", abiFile);
  console.log("");

  // Instrucciones de verificación en PolygonScan
  console.log("🔍 Para verificar el contrato en PolygonScan, ejecuta:");
  console.log(`npx hardhat verify --network ${hre.network.name} ${gaslessActionsAddress} "${SPHE_TOKEN_ADDRESS}" "${TREASURY_ADDRESS}"`);
  console.log("");

  console.log("✅ Deployment completado exitosamente!");
  console.log("");
  console.log("📋 Próximos pasos:");
  console.log("1. Aprobar el contrato GaslessActions para gastar tokens SPHE:");
  console.log(`   await spheToken.approve("${gaslessActionsAddress}", ethers.MaxUint256)`);
  console.log("");
  console.log("2. Configurar el contrato en el backend:");
  console.log(`   GASLESS_ACTIONS_CONTRACT="${gaslessActionsAddress}"`);
  console.log("");
  console.log("3. Actualizar límites si es necesario:");
  console.log(`   await gaslessActions.updateMaxAmount(ActionType.TIP, ethers.parseEther("2000"))`);
  console.log("");
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
