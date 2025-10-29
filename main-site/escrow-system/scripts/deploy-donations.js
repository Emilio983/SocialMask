const hre = require("hardhat");

async function main() {
    console.log("📦 Deploying Donations Contract to Polygon Amoy Testnet...\n");

    // Obtener el signer
    const [deployer] = await hre.ethers.getSigners();
    console.log("🔑 Deploying with account:", deployer.address);
    console.log("💰 Account balance:", hre.ethers.formatEther(await hre.ethers.provider.getBalance(deployer.address)), "MATIC\n");

    // Parámetros del contrato
    const TREASURY_ADDRESS = deployer.address; // Puedes cambiarlo después con updateTreasury()
    const FEE_PERCENTAGE = 250; // 2.5% (250 basis points)
    const MIN_DONATION = hre.ethers.parseEther("0.01"); // 0.01 tokens mínimo

    console.log("⚙️  Contract Parameters:");
    console.log("   Treasury Address:", TREASURY_ADDRESS);
    console.log("   Fee Percentage:", FEE_PERCENTAGE / 100, "%");
    console.log("   Min Donation:", hre.ethers.formatEther(MIN_DONATION), "tokens\n");

    // Compilar contratos
    console.log("🔨 Compiling contracts...");
    await hre.run("compile");

    // Desplegar el contrato
    console.log("\n🚀 Deploying Donations contract...");
    const Donations = await hre.ethers.getContractFactory("Donations");
    const donations = await Donations.deploy(TREASURY_ADDRESS, FEE_PERCENTAGE, MIN_DONATION);
    
    await donations.waitForDeployment();
    const donationsAddress = await donations.getAddress();

    console.log("✅ Donations contract deployed to:", donationsAddress);

    // Esperar confirmaciones antes de verificar
    console.log("\n⏳ Waiting for block confirmations...");
    await donations.deploymentTransaction().wait(5);

    // Configurar tokens permitidos
    console.log("\n🪙 Configuring allowed tokens...");
    
    // SPHE Token desde .env
    const SPHE_TOKEN = process.env.SPHE_TOKEN_ADDRESS || "0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b";
    
    // MATIC (wrapped MATIC en Polygon)
    const WMATIC_TOKEN = hre.network.name === "polygon" 
        ? "0x0d500B1d8E8eF31E21C99d1Db9A6444d3ADf1270" // Polygon Mainnet
        : "0x9c3C9283D3e44854697Cd22D3Faa240Cfb032889"; // Polygon Amoy Testnet
    
    if (SPHE_TOKEN && SPHE_TOKEN !== "0x0000000000000000000000000000000000000000") {
        console.log("   Allowing SPHE Token:", SPHE_TOKEN);
        const tx1 = await donations.setTokenAllowance(SPHE_TOKEN, true);
        await tx1.wait();
        console.log("   ✓ SPHE Token allowed");
        
        console.log("   Allowing WMATIC:", WMATIC_TOKEN);
        const tx2 = await donations.setTokenAllowance(WMATIC_TOKEN, true);
        await tx2.wait();
        console.log("   ✓ WMATIC Token allowed");
    } else {
        console.log("   ⚠️  SPHE Token address not configured - Update manually");
    }

    // Verificar contrato en PolygonScan
    if (hre.network.name !== "hardhat" && hre.network.name !== "localhost") {
        console.log("\n🔍 Verifying contract on PolygonScan...");
        try {
            await hre.run("verify:verify", {
                address: donationsAddress,
                constructorArguments: [TREASURY_ADDRESS, FEE_PERCENTAGE, MIN_DONATION],
            });
            console.log("✅ Contract verified successfully!");
        } catch (error) {
            console.log("❌ Verification failed:", error.message);
        }
    }

    // Resumen
    console.log("\n" + "=".repeat(60));
    console.log("📋 DEPLOYMENT SUMMARY");
    console.log("=".repeat(60));
    console.log("Network:", hre.network.name);
    console.log("Contract Address:", donationsAddress);
    console.log("Treasury Address:", TREASURY_ADDRESS);
    console.log("Fee Percentage:", FEE_PERCENTAGE / 100, "%");
    console.log("Min Donation:", hre.ethers.formatEther(MIN_DONATION), "tokens");
    console.log("Deployer:", deployer.address);
    console.log("=".repeat(60));

    console.log("\n📝 Next Steps:");
    console.log("1. Update .env file with:");
    console.log(`   DONATION_CONTRACT_ADDRESS=${donationsAddress}`);
    console.log("2. Configure allowed tokens:");
    console.log(`   await donations.setTokenAllowance(TOKEN_ADDRESS, true)`);
    console.log("3. Update treasury address if needed:");
    console.log(`   await donations.updateTreasury(NEW_TREASURY_ADDRESS)`);
    console.log("4. Test donations on testnet");
    console.log("5. Deploy to mainnet when ready\n");

    // Actualizar automáticamente el frontend
    console.log("🔄 Updating frontend files...");
    try {
        const { exec } = require('child_process');
        const updateScript = require('path').join(__dirname, 'update-frontend-addresses.js');
        
        exec(`node "${updateScript}" ${hre.network.name} ${donationsAddress}`, (error, stdout, stderr) => {
            if (error) {
                console.log("⚠️  Could not auto-update frontend. Run manually:");
                console.log(`   node scripts/update-frontend-addresses.js ${hre.network.name} ${donationsAddress}\n`);
            } else {
                console.log(stdout);
            }
        });
    } catch (e) {
        console.log("⚠️  Could not auto-update frontend. Run manually:");
        console.log(`   node scripts/update-frontend-addresses.js ${hre.network.name} ${donationsAddress}\n`);
    }
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error(error);
        process.exit(1);
    });
