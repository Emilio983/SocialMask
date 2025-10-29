const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
    console.log("🚀 Iniciando deployment de PayPerView...\n");
    
    const [deployer] = await hre.ethers.getSigners();
    const network = hre.network.name;
    
    console.log("📊 Información de Deployment:");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log(`Network:     ${network}`);
    console.log(`Deployer:    ${deployer.address}`);
    console.log(`Balance:     ${hre.ethers.formatEther(await hre.ethers.provider.getBalance(deployer.address))} MATIC`);
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    
    // Leer configuración
    const configPath = path.join(__dirname, "../config/contracts.json");
    let config = {};
    
    if (fs.existsSync(configPath)) {
        config = JSON.parse(fs.readFileSync(configPath, "utf8"));
    }
    
    // Obtener direcciones necesarias
    const spheTokenAddress = config.spheToken?.[network];
    let platformWallet = config.platformWallet?.[network];
    
    if (!spheTokenAddress) {
        throw new Error(`❌ SPHE Token no encontrado para ${network}. Deploy SPHE primero.`);
    }
    
    // Si no hay platform wallet configurada, usar deployer
    if (!platformWallet) {
        console.log("⚠️  Platform wallet no configurada, usando deployer address");
        platformWallet = deployer.address;
    }
    
    console.log("📝 Parámetros del contrato:");
    console.log(`SPHE Token:       ${spheTokenAddress}`);
    console.log(`Platform Wallet:  ${platformWallet}\n`);
    
    // Deploy PayPerView
    console.log("📦 Deploying PayPerView...");
    const PayPerView = await hre.ethers.getContractFactory("PayPerView");
    const payPerView = await PayPerView.deploy(spheTokenAddress, platformWallet);
    
    await payPerView.waitForDeployment();
    const payPerViewAddress = await payPerView.getAddress();
    
    console.log(`✅ PayPerView deployed: ${payPerViewAddress}\n`);
    
    // Verificar deployment
    console.log("🔍 Verificando configuración...");
    const configuredToken = await payPerView.spheToken();
    const configuredWallet = await payPerView.platformWallet();
    const platformFee = await payPerView.platformFee();
    
    console.log(`Token verificado:   ${configuredToken === spheTokenAddress ? "✅" : "❌"}`);
    console.log(`Wallet verificado:  ${configuredWallet === platformWallet ? "✅" : "❌"}`);
    console.log(`Platform Fee:       ${platformFee.toString()} (${Number(platformFee) / 100}%)\n`);
    
    // Guardar en config
    if (!config.payPerView) {
        config.payPerView = {};
    }
    config.payPerView[network] = payPerViewAddress;
    
    fs.writeFileSync(configPath, JSON.stringify(config, null, 2));
    console.log(`💾 Configuración guardada en ${configPath}\n`);
    
    // Generar ABI
    const abiPath = path.join(__dirname, "../abis/PayPerView.json");
    const artifact = await hre.artifacts.readArtifact("PayPerView");
    
    fs.writeFileSync(
        abiPath,
        JSON.stringify({
            address: payPerViewAddress,
            abi: artifact.abi,
            network: network,
            deployedAt: new Date().toISOString()
        }, null, 2)
    );
    console.log(`📄 ABI guardada en ${abiPath}\n`);
    
    // Actualizar frontend config
    const frontendConfigPath = path.join(__dirname, "../../assets/js/config.js");
    if (fs.existsSync(frontendConfigPath)) {
        let frontendConfig = fs.readFileSync(frontendConfigPath, "utf8");
        
        // Buscar y reemplazar o agregar PayPerView
        if (frontendConfig.includes("PAYPERVIEW_CONTRACT_ADDRESS")) {
            frontendConfig = frontendConfig.replace(
                /const PAYPERVIEW_CONTRACT_ADDRESS = ['"]0x[a-fA-F0-9]{40}['"]/,
                `const PAYPERVIEW_CONTRACT_ADDRESS = '${payPerViewAddress}'`
            );
        } else {
            // Agregar después de Donations
            frontendConfig = frontendConfig.replace(
                /(const DONATIONS_CONTRACT_ADDRESS = ['"]0x[a-fA-F0-9]{40}['"];)/,
                `$1\nconst PAYPERVIEW_CONTRACT_ADDRESS = '${payPerViewAddress}';`
            );
        }
        
        fs.writeFileSync(frontendConfigPath, frontendConfig);
        console.log(`🎨 Frontend config actualizado: ${frontendConfigPath}\n`);
    }
    
    // Verificar en block explorer
    if (network !== "hardhat" && network !== "localhost") {
        console.log("⏳ Esperando 30 segundos antes de verificar en explorer...");
        await new Promise(resolve => setTimeout(resolve, 30000));
        
        console.log("\n🔍 Verificando contrato en block explorer...");
        try {
            await hre.run("verify:verify", {
                address: payPerViewAddress,
                constructorArguments: [spheTokenAddress, platformWallet],
            });
            console.log("✅ Contrato verificado en block explorer\n");
        } catch (error) {
            if (error.message.includes("Already Verified")) {
                console.log("✅ Contrato ya estaba verificado\n");
            } else {
                console.log(`⚠️  Error verificando: ${error.message}\n`);
                console.log("Puedes verificar manualmente con:");
                console.log(`npx hardhat verify --network ${network} ${payPerViewAddress} ${spheTokenAddress} ${platformWallet}\n`);
            }
        }
    }
    
    // Resumen final
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log("✅ DEPLOYMENT COMPLETADO");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log(`Network:          ${network}`);
    console.log(`PayPerView:       ${payPerViewAddress}`);
    console.log(`SPHE Token:       ${spheTokenAddress}`);
    console.log(`Platform Wallet:  ${platformWallet}`);
    console.log(`Platform Fee:     ${Number(platformFee) / 100}%`);
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    
    // Próximos pasos
    console.log("📋 PRÓXIMOS PASOS:");
    console.log("1. ✅ Smart contract deployed");
    console.log("2. 🔄 Configurar Gelato Relay (Sub-Fase 4.2)");
    console.log("3. 🔄 Crear Backend APIs (Sub-Fase 4.3)");
    console.log("4. 🔄 Implementar Frontend UI (Sub-Fase 4.4)\n");
    
    console.log("💡 COMANDOS ÚTILES:");
    console.log(`npx hardhat verify --network ${network} ${payPerViewAddress} ${spheTokenAddress} ${platformWallet}`);
    console.log(`npx hardhat test test/PayPerView.test.js`);
    console.log(`npx hardhat run scripts/verify-paywall.js --network ${network}\n`);
    
    return {
        payPerView: payPerViewAddress,
        spheToken: spheTokenAddress,
        platformWallet: platformWallet
    };
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("❌ Error durante deployment:", error);
        process.exit(1);
    });

module.exports = main;
