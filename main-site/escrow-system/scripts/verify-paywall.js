const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
    console.log("🔍 Verificando sistema PayPerView...\n");
    
    const network = hre.network.name;
    const [signer] = await hre.ethers.getSigners();
    
    console.log("📊 Network:", network);
    console.log("👤 Signer:", signer.address);
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    
    // Leer configuración
    const configPath = path.join(__dirname, "../config/contracts.json");
    if (!fs.existsSync(configPath)) {
        console.error("❌ Config file not found:", configPath);
        process.exit(1);
    }
    
    const config = JSON.parse(fs.readFileSync(configPath, "utf8"));
    const payPerViewAddress = config.payPerView?.[network];
    const spheTokenAddress = config.spheToken?.[network];
    
    if (!payPerViewAddress) {
        console.error(`❌ PayPerView not deployed on ${network}`);
        process.exit(1);
    }
    
    if (!spheTokenAddress) {
        console.error(`❌ SPHE Token not found on ${network}`);
        process.exit(1);
    }
    
    // Conectar a contratos
    const PayPerView = await hre.ethers.getContractFactory("PayPerView");
    const payPerView = PayPerView.attach(payPerViewAddress);
    
    const SPHE = await hre.ethers.getContractFactory("MockERC20");
    const sphe = SPHE.attach(spheTokenAddress);
    
    console.log("📝 Contratos cargados:");
    console.log(`PayPerView: ${payPerViewAddress}`);
    console.log(`SPHE Token: ${spheTokenAddress}\n`);
    
    // ============================================
    // 1. VERIFICAR CONFIGURACIÓN
    // ============================================
    console.log("1️⃣  VERIFICANDO CONFIGURACIÓN");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    const configuredToken = await payPerView.spheToken();
    const platformWallet = await payPerView.platformWallet();
    const platformFee = await payPerView.platformFee();
    const nextContentId = await payPerView.nextContentId();
    const owner = await payPerView.owner();
    
    console.log(`✅ SPHE Token:        ${configuredToken}`);
    console.log(`   Matches config:    ${configuredToken === spheTokenAddress ? "✅" : "❌"}`);
    console.log(`✅ Platform Wallet:   ${platformWallet}`);
    console.log(`✅ Platform Fee:      ${platformFee.toString()} (${Number(platformFee) / 100}%)`);
    console.log(`✅ Owner:             ${owner}`);
    console.log(`✅ Next Content ID:   ${nextContentId.toString()}\n`);
    
    // ============================================
    // 2. VERIFICAR SPHE TOKEN
    // ============================================
    console.log("2️⃣  VERIFICANDO SPHE TOKEN");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    const tokenName = await sphe.name();
    const tokenSymbol = await sphe.symbol();
    const tokenDecimals = await sphe.decimals();
    const signerBalance = await sphe.balanceOf(signer.address);
    
    console.log(`✅ Name:              ${tokenName}`);
    console.log(`✅ Symbol:            ${tokenSymbol}`);
    console.log(`✅ Decimals:          ${tokenDecimals}`);
    console.log(`✅ Signer Balance:    ${hre.ethers.formatEther(signerBalance)} SPHE\n`);
    
    // ============================================
    // 3. TEST CREAR CONTENIDO
    // ============================================
    console.log("3️⃣  TESTEANDO CREAR CONTENIDO");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    try {
        const testPrice = hre.ethers.parseEther("10");
        console.log(`Creando contenido de prueba (${hre.ethers.formatEther(testPrice)} SPHE)...`);
        
        const tx = await payPerView.createContent(testPrice);
        const receipt = await tx.wait();
        
        const event = receipt.logs
            .map(log => {
                try {
                    return payPerView.interface.parseLog(log);
                } catch {
                    return null;
                }
            })
            .find(e => e && e.name === "ContentCreated");
        
        if (event) {
            console.log(`✅ Contenido creado con ID: ${event.args[0].toString()}`);
            console.log(`   Creator: ${event.args[1]}`);
            console.log(`   Price: ${hre.ethers.formatEther(event.args[2])} SPHE`);
            console.log(`   TX Hash: ${receipt.hash}\n`);
            
            // Verificar contenido
            const contentId = event.args[0];
            const content = await payPerView.contents(contentId);
            console.log("📄 Contenido verificado:");
            console.log(`   Active: ${content.active}`);
            console.log(`   Total Sales: ${content.totalSales.toString()}`);
            console.log(`   Total Revenue: ${hre.ethers.formatEther(content.totalRevenue)} SPHE\n`);
        }
        
    } catch (error) {
        console.error("❌ Error creando contenido:", error.message, "\n");
    }
    
    // ============================================
    // 4. ESTADÍSTICAS GLOBALES
    // ============================================
    console.log("4️⃣  ESTADÍSTICAS GLOBALES");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    try {
        const [totalContents, totalActiveContents] = await payPerView.getGlobalStats();
        console.log(`✅ Total Contents:    ${totalContents.toString()}`);
        console.log(`✅ Active Contents:   ${totalActiveContents.toString()}\n`);
        
        // Mostrar información de cada contenido
        if (totalContents > 0n) {
            console.log("📋 CONTENIDOS EXISTENTES:");
            console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            
            for (let i = 1n; i <= totalContents; i++) {
                const [creator, price, active, totalSales, totalRevenue] = 
                    await payPerView.getContentInfo(i);
                
                console.log(`\nContent #${i}:`);
                console.log(`  Creator:       ${creator}`);
                console.log(`  Price:         ${hre.ethers.formatEther(price)} SPHE`);
                console.log(`  Active:        ${active ? "✅" : "❌"}`);
                console.log(`  Total Sales:   ${totalSales.toString()}`);
                console.log(`  Total Revenue: ${hre.ethers.formatEther(totalRevenue)} SPHE`);
                
                // Verificar si el signer tiene acceso
                const hasAccess = await payPerView.hasContentAccess(i, signer.address);
                console.log(`  Your Access:   ${hasAccess ? "✅" : "❌"}`);
                
                // Mostrar balance del creador
                if (creator.toLowerCase() === signer.address.toLowerCase()) {
                    const balance = await payPerView.creatorBalances(creator);
                    console.log(`  Your Balance:  ${hre.ethers.formatEther(balance)} SPHE`);
                }
            }
            console.log();
        }
        
    } catch (error) {
        console.error("❌ Error obteniendo estadísticas:", error.message, "\n");
    }
    
    // ============================================
    // 5. RESUMEN FINAL
    // ============================================
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log("✅ VERIFICACIÓN COMPLETADA");
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    console.log(`Network:              ${network}`);
    console.log(`PayPerView Address:   ${payPerViewAddress}`);
    console.log(`SPHE Token Address:   ${spheTokenAddress}`);
    console.log(`Platform Fee:         ${Number(platformFee) / 100}%`);
    console.log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
    
    // Explorer links
    if (network === "amoy") {
        console.log("🔗 EXPLORER LINKS:");
        console.log(`PayPerView: https://amoy.polygonscan.com/address/${payPerViewAddress}`);
        console.log(`SPHE Token: https://amoy.polygonscan.com/address/${spheTokenAddress}\n`);
    } else if (network === "polygon") {
        console.log("🔗 EXPLORER LINKS:");
        console.log(`PayPerView: https://polygonscan.com/address/${payPerViewAddress}`);
        console.log(`SPHE Token: https://polygonscan.com/address/${spheTokenAddress}\n`);
    }
    
    console.log("💡 COMANDOS ÚTILES:");
    console.log(`npx hardhat test test/PayPerView.test.js`);
    console.log(`npx hardhat run scripts/deploy-paywall.js --network ${network}`);
    console.log(`npx hardhat verify --network ${network} ${payPerViewAddress} ${spheTokenAddress} ${platformWallet}\n`);
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("❌ Error:", error);
        process.exit(1);
    });
